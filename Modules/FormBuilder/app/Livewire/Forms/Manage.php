<?php

namespace Modules\FormBuilder\Livewire\Forms;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    public string $pdfTemplate = '';

    public bool $editingDetails = false;

    public string $editName = '';

    public string $editDescription = '';

    public string $editSlug = '';

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
        $this->pdfTemplate = Form::find($formId)?->pdf_template ?? '';
        $this->editingDetails = false;
    }

    public function cancelSelection()
    {
        $this->selectedFormId = null;
        $this->pdfTemplate = '';
        $this->editingDetails = false;
    }

    public function editDetails()
    {
        $form = Form::find($this->selectedFormId);

        if (! $form) {
            return;
        }

        $this->editName = $form->name;
        $this->editDescription = $form->description ?? '';
        $this->editSlug = $form->slug;
        $this->editingDetails = true;
    }

    public function cancelEditDetails()
    {
        $this->editingDetails = false;
        $this->resetErrorBag(['editName', 'editDescription', 'editSlug']);
    }

    public function saveDetails()
    {
        $form = Form::find($this->selectedFormId);

        if (! $form) {
            return;
        }

        $this->editSlug = Str::slug($this->editSlug);

        $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editDescription' => ['nullable', 'string'],
            'editSlug' => ['required', 'string', 'max:255', Rule::unique('forms', 'slug')->ignore($form->id)],
        ]);

        $form->update([
            'name' => $this->editName,
            'description' => $this->editDescription !== '' ? $this->editDescription : null,
            'slug' => $this->editSlug,
        ]);

        $this->editingDetails = false;
        session()->flash('status', 'Formulario actualizado.');
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

    public function savePdfTemplate()
    {
        $form = Form::find($this->selectedFormId);

        if (! $form) {
            return;
        }

        $this->validate([
            'pdfTemplate' => ['nullable', Rule::in(array_keys(Form::PDF_TEMPLATES))],
        ]);

        $form->update(['pdf_template' => $this->pdfTemplate !== '' ? $this->pdfTemplate : null]);

        session()->flash('status', 'Formato de PDF/impresión actualizado.');
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
            'pdfTemplates' => Form::PDF_TEMPLATES,
        ]);
    }
}
