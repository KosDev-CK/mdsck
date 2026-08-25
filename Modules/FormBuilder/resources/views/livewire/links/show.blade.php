<div>
    @push('page-title')
        Respuestas — Ticket {{ $ticketFormLink->ticket_number }}
    @endpush
    @push('page-actions')
        <a href="{{ route('formbuilder.links.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            &larr; Volver a Mis Formularios
        </a>
    @endpush

    <x-ui.card padding="p-5" class="mb-6">
        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Formulario</dt>
                <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ $ticketFormLink->form->name }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Correo destino</dt>
                <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ $ticketFormLink->recipient_email }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Respondido</dt>
                <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ $ticketFormLink->submission?->submitted_at->format('d/m/Y H:i') }}</dd>
            </div>
        </dl>
    </x-ui.card>

    <x-ui.card padding="p-5">
        @if ($ticketFormLink->submission)
            @php $answersByField = $ticketFormLink->submission->answers->keyBy('form_field_id'); @endphp

            <div class="space-y-4">
                @foreach ($ticketFormLink->form->fields as $field)
                    <div>
                        @if ($field->type === 'label')
                            <div class="rounded-md bg-gray-50 dark:bg-gray-800/50 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">
                                {{ $field->label }}
                            </div>
                        @elseif ($field->type === 'repeater')
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $field->label }}</div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm border border-gray-100 dark:border-gray-800 rounded-md">
                                    <thead>
                                        <tr class="text-left text-gray-500 border-b border-gray-100 dark:text-gray-400 dark:border-gray-800">
                                            @foreach ($field->children as $child)
                                                <th class="py-2 px-2">{{ $child->label }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ((array) ($answersByField[$field->id]->value ?? []) as $row)
                                            <tr class="border-b border-gray-50 dark:border-gray-800">
                                                @foreach ($field->children as $child)
                                                    <td class="py-2 px-2 align-top">{{ $child->formatValue($row[$child->field_key] ?? null) ?: '—' }}</td>
                                                @endforeach
                                            </tr>
                                        @empty
                                            <tr>
                                                <td class="py-3 px-2 text-center text-gray-400 dark:text-gray-500" colspan="{{ max($field->children->count(), 1) }}">
                                                    Sin filas capturadas.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $field->label }}</div>
                            <div class="text-sm text-gray-900 dark:text-gray-100">
                                {{ $answersByField[$field->id]?->displayValue() ?: '—' }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="py-16 text-center text-gray-400 dark:text-gray-500">
                Este enlace todavía no ha sido respondido.
            </div>
        @endif
    </x-ui.card>
</div>
