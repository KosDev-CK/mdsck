<div>
    @if (session('error'))
        <x-ui.alert variant="error" class="mb-4">{{ session('error') }}</x-ui.alert>
    @endif

    @if ($status === 'invalid')
        <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Enlace inválido</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Este enlace no existe o ya no es válido.</p>
    @elseif ($status === 'expired')
        <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Enlace vencido</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Este enlace ya expiró. Solicita uno nuevo a quien te lo compartió.</p>
    @elseif ($status === 'locked')
        <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Enlace bloqueado</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Este enlace se bloqueó por demasiados intentos de verificación. Solicita uno nuevo.</p>
    @elseif ($status === 'used' && $justSubmitted)
        <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">¡Gracias!</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Tu respuesta fue registrada correctamente.</p>
    @elseif ($status === 'used')
        <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Formulario ya respondido</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Este enlace ya fue utilizado y no puede llenarse de nuevo.</p>
    @elseif (! $verified)
        <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Confirma tu correo</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            Para continuar, escribe el correo al que se envió este enlace.
        </p>

        <form wire:submit="verifyEmail" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Correo</label>
                <input wire:model="confirmedEmail" type="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100" autofocus>
                @error('confirmedEmail') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <x-ui.button type="submit" class="w-full justify-center">Continuar</x-ui.button>
        </form>
    @else
        <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $currentForm->name }}</h1>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">{{ $currentForm->description }}</p>

        <form wire:submit="submit" class="space-y-4">
            @foreach ($currentForm->fields as $field)
                <div wire:key="answer-{{ $field->id }}">
                    @if ($field->type === 'label')
                        <div class="rounded-md bg-gray-50 dark:bg-gray-800/50 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">
                            {{ $field->label }}
                        </div>
                    @elseif ($field->type === 'timestamp')
                        <div class="text-xs text-gray-400 italic">
                            {{ $field->label }}: se registrará automáticamente al enviar.
                        </div>
                    @elseif ($field->type === 'repeater')
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ $field->label }}
                                @if ($field->is_required) <span class="text-danger">*</span> @endif
                            </label>
                            @if ($field->help_text)
                                <p class="text-xs text-gray-400 mb-2">{{ $field->help_text }}</p>
                            @endif

                            <div class="rounded-md border border-gray-100 dark:border-gray-800 p-3 mb-3">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">
                                    {{ isset($repeaterEditIndex[$field->field_key]) ? 'Editando registro' : 'Nuevo registro' }}
                                </p>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    @foreach ($field->children as $child)
                                        <div>
                                            <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">
                                                {{ $child->label }}@if($child->is_required)<span class="text-danger">*</span>@endif
                                            </label>
                                            @include('formbuilder::livewire.public._field-input', ['field' => $child, 'modelPath' => "repeaterDrafts.{$field->field_key}.{$child->field_key}"])
                                            @error("repeaterDrafts.{$field->field_key}.{$child->field_key}") <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                        </div>
                                    @endforeach
                                </div>
                                <div class="mt-3 flex gap-2">
                                    <x-ui.button type="button" variant="secondary" size="sm" wire:click="saveRepeaterRow('{{ $field->field_key }}')">
                                        {{ isset($repeaterEditIndex[$field->field_key]) ? 'Guardar registro' : '+ Agregar registro' }}
                                    </x-ui.button>
                                    @if (isset($repeaterEditIndex[$field->field_key]))
                                        <x-ui.button type="button" variant="secondary" size="sm" wire:click="cancelRepeaterEdit('{{ $field->field_key }}')">
                                            Cancelar
                                        </x-ui.button>
                                    @endif
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-sm border border-gray-100 dark:border-gray-800 rounded-md">
                                    <thead>
                                        <tr class="text-left text-gray-500 border-b border-gray-100 dark:text-gray-400 dark:border-gray-800">
                                            @foreach ($field->children as $child)
                                                <th class="py-2 px-2">{{ $child->label }}</th>
                                            @endforeach
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse (($answers[$field->field_key] ?? []) as $rowIndex => $row)
                                            <tr wire:key="row-{{ $field->id }}-{{ $rowIndex }}" class="border-b border-gray-50 dark:border-gray-800">
                                                @foreach ($field->children as $child)
                                                    <td class="py-2 px-2 align-top">{{ $child->formatValue($row[$child->field_key] ?? null) ?: '—' }}</td>
                                                @endforeach
                                                <td class="py-2 px-2 text-right align-top whitespace-nowrap">
                                                    <button type="button" wire:click="editRepeaterRow('{{ $field->field_key }}', {{ $rowIndex }})" class="text-primary hover:opacity-75 mr-2 text-xs">
                                                        Editar
                                                    </button>
                                                    <button type="button" wire:click="removeRepeaterRow('{{ $field->field_key }}', {{ $rowIndex }})" class="text-danger hover:opacity-75 text-xs">
                                                        Eliminar
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td class="py-3 px-2 text-center text-gray-400 dark:text-gray-500" colspan="{{ max($field->children->count(), 1) + 1 }}">
                                                    Sin registros capturados.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @error("answers.{$field->field_key}") <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $field->label }}
                            @if ($field->is_required) <span class="text-danger">*</span> @endif
                        </label>
                        @if ($field->help_text)
                            <p class="text-xs text-gray-400">{{ $field->help_text }}</p>
                        @endif

                        @include('formbuilder::livewire.public._field-input', ['field' => $field, 'modelPath' => "answers.{$field->field_key}"])

                        @error("answers.{$field->field_key}") <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    @endif
                </div>
            @endforeach

            <x-ui.button type="submit" class="w-full justify-center">Enviar</x-ui.button>
        </form>
    @endif
</div>
