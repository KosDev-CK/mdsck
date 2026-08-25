<?php

namespace Modules\FormBuilder\Livewire\Forms;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\FormBuilder\Models\Form;
use Modules\FormBuilder\Models\FormField;

#[Layout('layouts.app')]
class Builder extends Component
{
    public Form $form;

    public bool $showFieldPanel = false;

    public ?int $editingFieldId = null;

    public string $type = 'short_text';

    public string $label = '';

    public string $helpText = '';

    public array $options = [];

    public bool $isRequired = false;

    /** @var array<int, array{id: ?int, field_key: ?string, type: string, label: string, options: array, is_required: bool}> */
    public array $subFields = [];

    public function mount(Form $form)
    {
        $this->form = $form;
    }

    protected function rules(): array
    {
        $rules = [
            'label' => ['required', 'string', $this->type === 'label' ? 'max:2000' : 'max:255'],
            'type' => ['required', Rule::in(array_keys(FormField::TYPES))],
            'isRequired' => ['boolean'],
            'helpText' => ['nullable', 'string', 'max:500'],
        ];

        if (in_array($this->type, FormField::CHOICE_TYPES, true)) {
            $rules['options'] = ['required', 'array', 'min:1'];
            $rules['options.*.label'] = ['required', 'string', 'max:255'];
        }

        if ($this->type === 'repeater') {
            $rules['subFields'] = ['required', 'array', 'min:1'];
            $rules['subFields.*.label'] = ['required', 'string', 'max:255'];
            $rules['subFields.*.type'] = ['required', Rule::in(FormField::REPEATER_CHILD_TYPES)];
        }

        return $rules;
    }

    protected function validationAttributes(): array
    {
        return [
            'label' => 'etiqueta',
            'type' => 'tipo de campo',
            'helpText' => 'texto de ayuda',
            'options' => 'opciones',
            'options.*.label' => 'etiqueta de la opción',
            'subFields' => 'columnas',
            'subFields.*.label' => 'etiqueta de la columna',
            'subFields.*.type' => 'tipo de columna',
        ];
    }

    public function openNewFieldPanel()
    {
        $this->resetFieldForm();
        $this->showFieldPanel = true;
    }

    protected function resetFieldForm(): void
    {
        $this->editingFieldId = null;
        $this->type = 'short_text';
        $this->label = '';
        $this->helpText = '';
        $this->options = [];
        $this->isRequired = false;
        $this->subFields = [];
        $this->resetErrorBag();
    }

    public function editField(int $fieldId)
    {
        $field = $this->form->fields()->with('children')->find($fieldId);

        if (! $field) {
            return;
        }

        $this->editingFieldId = $field->id;
        $this->type = $field->type;
        $this->label = $field->label;
        $this->helpText = $field->help_text ?? '';
        $this->options = $field->options ?? [];
        $this->isRequired = $field->is_required;
        $this->subFields = $field->children->map(fn (FormField $child) => [
            'id' => $child->id,
            'field_key' => $child->field_key,
            'type' => $child->type,
            'label' => $child->label,
            'options' => $child->options ?? [],
            'is_required' => $child->is_required,
        ])->all();
        $this->showFieldPanel = true;
    }

    public function addOptionRow()
    {
        $this->options[] = ['value' => '', 'label' => ''];
    }

    public function removeOptionRow(int $index)
    {
        unset($this->options[$index]);
        $this->options = array_values($this->options);
    }

    public function addSubField()
    {
        $this->subFields[] = [
            'id' => null,
            'field_key' => null,
            'type' => 'short_text',
            'label' => '',
            'options' => [],
            'is_required' => false,
        ];
    }

    public function removeSubField(int $index)
    {
        unset($this->subFields[$index]);
        $this->subFields = array_values($this->subFields);
    }

    public function addSubFieldOptionRow(int $subIndex)
    {
        $this->subFields[$subIndex]['options'][] = ['value' => '', 'label' => ''];
    }

    public function removeSubFieldOptionRow(int $subIndex, int $optionIndex)
    {
        unset($this->subFields[$subIndex]['options'][$optionIndex]);
        $this->subFields[$subIndex]['options'] = array_values($this->subFields[$subIndex]['options']);
    }

    protected function normalizeOptions(array $options): array
    {
        return collect($options)
            ->filter(fn ($o) => trim($o['label'] ?? '') !== '')
            ->map(fn ($o) => [
                'value' => ($o['value'] ?? '') !== '' ? $o['value'] : Str::slug($o['label'], '_'),
                'label' => $o['label'],
            ])->values()->all();
    }

    /**
     * Sibling-dependent rule ("choice sub-fields need at least one option")
     * that a plain wildcard validation rule can't express cleanly.
     */
    protected function validateSubFieldOptions(): void
    {
        if ($this->type !== 'repeater') {
            return;
        }

        foreach ($this->subFields as $i => $sub) {
            if (in_array($sub['type'], FormField::CHOICE_TYPES, true) && empty($this->normalizeOptions($sub['options'] ?? []))) {
                $this->addError("subFields.$i.options", 'Agrega al menos una opción para esta columna.');
            }
        }
    }

    public function saveField()
    {
        $this->validate();
        $this->validateSubFieldOptions();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $options = in_array($this->type, FormField::CHOICE_TYPES, true)
            ? $this->normalizeOptions($this->options)
            : null;

        $attributes = [
            'type' => $this->type,
            'label' => $this->label,
            'help_text' => $this->helpText !== '' ? $this->helpText : null,
            'options' => $options,
            'is_required' => $this->isRequired,
        ];

        if ($this->editingFieldId) {
            $field = $this->form->fields()->find($this->editingFieldId);
            $field?->update($attributes);
        } else {
            $field = $this->form->fields()->create($attributes + [
                'order' => ($this->form->fields()->max('order') ?? 0) + 1,
            ]);
        }

        if ($this->type === 'repeater') {
            $this->syncChildren($field);
        } elseif ($field->children()->exists()) {
            $field->children()->delete();
        }

        $this->showFieldPanel = false;
        $this->resetFieldForm();
    }

    protected function syncChildren(FormField $parent): void
    {
        $existingIds = $parent->children()->pluck('id')->all();
        $keptIds = [];

        foreach ($this->subFields as $sub) {
            $options = in_array($sub['type'], FormField::CHOICE_TYPES, true)
                ? $this->normalizeOptions($sub['options'] ?? [])
                : null;

            if (! empty($sub['id']) && in_array($sub['id'], $existingIds, true)) {
                FormField::where('id', $sub['id'])->update([
                    'type' => $sub['type'],
                    'label' => $sub['label'],
                    'options' => $options,
                    'is_required' => $sub['is_required'] ?? false,
                ]);
                $keptIds[] = $sub['id'];
            } else {
                $child = $parent->children()->create([
                    'form_id' => $this->form->id,
                    'type' => $sub['type'],
                    'label' => $sub['label'],
                    'options' => $options,
                    'is_required' => $sub['is_required'] ?? false,
                    'order' => ($parent->children()->max('order') ?? 0) + 1,
                ]);
                $keptIds[] = $child->id;
            }
        }

        FormField::whereIn('id', array_diff($existingIds, $keptIds))->delete();
    }

    public function deleteField(int $fieldId)
    {
        $this->form->fields()->where('id', $fieldId)->delete();
    }

    public function duplicateField(int $fieldId)
    {
        $field = $this->form->fields()->with('children')->find($fieldId);

        if (! $field) {
            return;
        }

        $this->form->fields()->where('order', '>', $field->order)->increment('order');

        $duplicate = $this->form->fields()->create([
            'type' => $field->type,
            'label' => $field->label.' (copia)',
            'help_text' => $field->help_text,
            'options' => $field->options,
            'is_required' => $field->is_required,
            'order' => $field->order + 1,
        ]);

        foreach ($field->children as $index => $child) {
            $duplicate->children()->create([
                'form_id' => $this->form->id,
                'type' => $child->type,
                'label' => $child->label,
                'options' => $child->options,
                'is_required' => $child->is_required,
                'order' => $index,
            ]);
        }
    }

    public function reorderFields(array $orderedFieldIds)
    {
        foreach ($orderedFieldIds as $index => $fieldId) {
            $this->form->fields()->where('id', $fieldId)->update(['order' => $index]);
        }
    }

    public function render()
    {
        return view('formbuilder::livewire.forms.builder', [
            'fields' => $this->form->fields,
            'fieldTypes' => FormField::TYPES,
            'choiceTypes' => FormField::CHOICE_TYPES,
            'subFieldTypes' => collect(FormField::TYPES)->only(FormField::REPEATER_CHILD_TYPES)->all(),
        ]);
    }
}
