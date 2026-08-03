<?php

namespace Modules\Ejemplo\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Ejemplo\Models\EjemploItem;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $title = '';

    public string $description = '';

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function create()
    {
        $this->validate();

        EjemploItem::create([
            'title' => $this->title,
            'description' => $this->description,
            'created_by' => auth()->id(),
        ]);

        $this->reset(['title', 'description']);
    }

    public function delete(int $id)
    {
        EjemploItem::find($id)?->delete();
    }

    public function render()
    {
        return view('ejemplo::livewire.index', [
            'items' => EjemploItem::latest()->paginate(10),
        ]);
    }
}
