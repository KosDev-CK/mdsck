<?php

namespace Modules\FormBuilder\Livewire\Forms;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\FormBuilder\Models\Form;

#[Layout('layouts.app')]
class Manage extends Component
{
    use WithPagination;

    public string $name = '';

    public string $description = '';

    public ?int $selectedFormId = null;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function create()
    {
        $this->validate();

        $form = Form::create([
            'name' => $this->name,
            'description' => $this->description,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        $this->reset(['name', 'description']);
        $this->selectForm($form->id);
    }

    public function selectForm(int $formId)
    {
        $this->selectedFormId = $formId;
    }

    public function cancelSelection()
    {
        $this->selectedFormId = null;
    }

    public function togglePublish(int $formId)
    {
        $form = Form::find($formId);

        if (! $form) {
            return;
        }

        $form->update(['status' => $form->status === 'published' ? 'draft' : 'published']);

        session()->flash('status', $form->status === 'published' ? 'Formulario publicado.' : 'Formulario despublicado.');
    }

    public function delete(int $formId)
    {
        $form = Form::find($formId);

        if (! $form) {
            return;
        }

        if ($form->submissions()->exists() || $form->ticketFormLinks()->exists()) {
            session()->flash('error', 'No se puede eliminar una plantilla con enlaces o respuestas generadas.');

            return;
        }

        $form->delete();

        if ($this->selectedFormId === $formId) {
            $this->cancelSelection();
        }

        session()->flash('status', 'Formulario eliminado.');
    }

    public function render()
    {
        return view('formbuilder::livewire.forms.manage', [
            'forms' => Form::withCount(['fields', 'submissions'])->latest()->paginate(10),
            'selectedForm' => $this->selectedFormId ? Form::find($this->selectedFormId) : null,
        ]);
    }
}
