@php
    $hasChoiceOptions = in_array($type, $choiceTypes, true);
    $isLabelType = $type === 'label';
    $isTimestampType = $type === 'timestamp';
    $isRepeaterType = $type === 'repeater';
@endphp

<div>
    @push('page-title')
        Construir: {{ $form->name }}
    @endpush
    @push('page-actions')
        <a href="{{ route('formbuilder.forms.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            &larr; Volver a Formularios
        </a>
    @endpush
    <p class="text-sm text-gray-500 mb-6 dark:text-gray-400">
        Agrega campos, ajusta su orden arrastrándolos, y define si son obligatorios.
    </p>

    <x-ui.card padding="p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Campos</h2>
            <x-ui.button size="sm" wire:click="openNewFieldPanel">Agregar campo</x-ui.button>
        </div>

        <div
            x-data
            x-init="window.FormBuilderSortable && window.FormBuilderSortable.init($el, $wire)"
            class="space-y-2"
        >
            @forelse ($fields as $field)
                <div wire:key="field-{{ $field->id }}" data-field-id="{{ $field->id }}" class="flex items-center gap-3 rounded-md border border-gray-100 bg-gray-50/50 px-3 py-2 dark:border-gray-800 dark:bg-gray-800/30">
                    <span class="drag-handle cursor-move text-gray-300 dark:text-gray-600" title="Arrastrar para reordenar">
                        <x-heroicon-o-bars-3 class="h-5 w-5" />
                    </span>

                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            @if ($field->type === 'label')
                                <x-ui.badge color="gray">Etiqueta</x-ui.badge>
                            @endif
                            <span class="truncate">{{ $field->label }}</span>
                            @if ($field->is_required)
                                <span class="text-danger">*</span>
                            @endif
                            @if ($field->help_text)
                                <x-heroicon-o-information-circle class="h-4 w-4 text-gray-400 shrink-0" title="{{ $field->help_text }}" />
                            @endif
                        </div>
                        <div class="text-xs text-gray-400">
                            {{ $field->typeLabel() }}
                            @if ($field->type === 'repeater')
                                &middot; {{ $field->children->count() }} columna(s)
                            @endif
                        </div>
                    </div>

                    <button wire:click="duplicateField({{ $field->id }})" class="text-sm text-gray-500 hover:text-primary dark:text-gray-400">
                        Duplicar
                    </button>
                    <button wire:click="editField({{ $field->id }})" class="text-sm text-gray-500 hover:text-primary dark:text-gray-400">
                        Editar
                    </button>
                    <button wire:click="deleteField({{ $field->id }})" wire:confirm="¿Eliminar este campo?" class="text-sm text-red-600 hover:text-red-500 dark:text-red-400">
                        Eliminar
                    </button>
                </div>
            @empty
                <div class="py-10 text-center text-gray-400 dark:text-gray-500">
                    Este formulario no tiene campos todavía.
                </div>
            @endforelse
        </div>
    </x-ui.card>

    <x-ui.modal model="showFieldPanel" max-width="max-w-2xl" :title="$editingFieldId ? 'Editar campo' : 'Agregar campo'">
        <form wire:submit="saveField" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ $isLabelType ? 'Texto a mostrar' : 'Etiqueta' }}
                </label>
                @if ($isLabelType)
                    <textarea wire:model="label" rows="3" placeholder="Instrucciones o encabezado de sección" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"></textarea>
                @else
                    <input wire:model="label" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                @endif
                @error('label') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo de campo</label>
                <select wire:model.live="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                    @foreach ($fieldTypes as $value => $labelText)
                        <option value="{{ $value }}">{{ $labelText }}</option>
                    @endforeach
                </select>
            </div>

            @unless ($isLabelType || $isTimestampType)
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Texto de ayuda (opcional)</label>
                    <textarea wire:model="helpText" rows="2" placeholder="Se muestra junto al campo al llenar el formulario" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"></textarea>
                    @error('helpText') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            @endunless

            @if ($hasChoiceOptions)
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Opciones</label>
                    <div class="space-y-2">
                        @foreach ($options as $i => $option)
                            <div class="flex items-center gap-2">
                                <input wire:model="options.{{ $i }}.label" type="text" placeholder="Opción" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                                <button type="button" wire:click="removeOptionRow({{ $i }})" class="text-red-600 hover:text-red-500 dark:text-red-400">
                                    <x-heroicon-o-x-mark class="h-4 w-4" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                    @error('options') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    <button type="button" wire:click="addOptionRow" class="mt-2 text-sm text-primary hover:underline">
                        + Agregar opción
                    </button>
                </div>
            @endif

            @if ($isRepeaterType)
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Columnas de la tabla</label>
                    <div class="space-y-3">
                        @foreach ($subFields as $i => $sub)
                            <div class="rounded-md border border-gray-100 dark:border-gray-800 p-3 space-y-2">
                                <div class="flex items-center gap-2">
                                    <input wire:model="subFields.{{ $i }}.label" type="text" placeholder="Etiqueta de la columna" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                                    <select wire:model.live="subFields.{{ $i }}.type" class="rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                                        @foreach ($subFieldTypes as $value => $labelText)
                                            <option value="{{ $value }}">{{ $labelText }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" wire:click="removeSubField({{ $i }})" class="text-red-600 hover:text-red-500 dark:text-red-400">
                                        <x-heroicon-o-x-mark class="h-4 w-4" />
                                    </button>
                                </div>
                                @error("subFields.{$i}.label") <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                                @if (in_array($sub['type'], $choiceTypes, true))
                                    <div class="pl-2 space-y-1">
                                        @foreach (($sub['options'] ?? []) as $j => $option)
                                            <div class="flex items-center gap-2">
                                                <input wire:model="subFields.{{ $i }}.options.{{ $j }}.label" type="text" placeholder="Opción" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                                                <button type="button" wire:click="removeSubFieldOptionRow({{ $i }}, {{ $j }})" class="text-red-600 hover:text-red-500 dark:text-red-400">
                                                    <x-heroicon-o-x-mark class="h-4 w-4" />
                                                </button>
                                            </div>
                                        @endforeach
                                        @error("subFields.{$i}.options") <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                        <button type="button" wire:click="addSubFieldOptionRow({{ $i }})" class="text-sm text-primary hover:underline">
                                            + Agregar opción
                                        </button>
                                    </div>
                                @endif

                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input wire:model="subFields.{{ $i }}.is_required" type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary dark:border-gray-600 dark:bg-gray-800">
                                    Obligatoria en cada fila
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('subFields') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    <button type="button" wire:click="addSubField" class="mt-2 text-sm text-primary hover:underline">
                        + Agregar columna
                    </button>
                </div>
            @endif

            @unless ($isLabelType || $isTimestampType)
                <div class="flex items-center gap-2">
                    <input wire:model="isRequired" type="checkbox" id="isRequired" class="rounded border-gray-300 text-primary focus:ring-primary dark:border-gray-600 dark:bg-gray-800">
                    <label for="isRequired" class="text-sm text-gray-700 dark:text-gray-300">
                        {{ $isRepeaterType ? 'Obligatorio agregar al menos una fila' : 'Campo obligatorio' }}
                    </label>
                </div>
            @endunless

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" variant="secondary" x-on:click="open = false">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar campo</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    @vite('resources/js/formbuilder-sortable.js')
</div>
