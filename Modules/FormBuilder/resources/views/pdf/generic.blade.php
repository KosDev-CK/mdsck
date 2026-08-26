<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $form->name }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1f2937; margin: 24px; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        p.description { font-size: 10px; color: #6b7280; margin: 0 0 16px; }
        p.meta { font-size: 9px; color: #9ca3af; margin: 0 0 20px; }
        table.fields { width: 100%; border-collapse: collapse; }
        table.fields td { padding: 6px 4px; vertical-align: top; }
        td.label { font-weight: bold; width: 220px; border-bottom: 1px solid #d1d5db; }
        td.value { border-bottom: 1px solid #d1d5db; }
        div.section-label { background: #f3f4f6; padding: 6px 8px; font-weight: bold; white-space: pre-line; margin: 10px 0 4px; }
        table.repeater { width: 100%; border-collapse: collapse; margin: 4px 0 10px; }
        table.repeater th, table.repeater td { border: 1px solid #d1d5db; padding: 4px 6px; font-size: 10px; text-align: left; }
    </style>
</head>
<body>
    <h1>{{ $form->name }}</h1>
    @if ($form->description)
        <p class="description">{{ $form->description }}</p>
    @endif
    <p class="meta">
        Ticket {{ $link->ticket_number }} &middot; {{ $link->recipient_email }} &middot;
        Respondido el {{ $link->submission?->submitted_at->format('d/m/Y H:i') }}
    </p>

    @php $answersByField = $link->submission?->answers->keyBy('form_field_id') ?? collect(); @endphp

    <table class="fields">
        @foreach ($form->fields as $field)
            @if ($field->type === 'label')
                <tr>
                    <td colspan="2"><div class="section-label">{{ $field->label }}</div></td>
                </tr>
            @elseif ($field->type === 'repeater')
                <tr>
                    <td colspan="2">
                        <strong>{{ $field->label }}</strong>
                        <table class="repeater">
                            <thead>
                                <tr>
                                    @foreach ($field->children as $child)
                                        <th>{{ $child->label }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ((array) ($answersByField[$field->id]->value ?? []) as $row)
                                    <tr>
                                        @foreach ($field->children as $child)
                                            <td>{{ $child->formatValue($row[$child->field_key] ?? null) ?: '-' }}</td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ max($field->children->count(), 1) }}">Sin filas capturadas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </td>
                </tr>
            @else
                <tr>
                    <td class="label">{{ $field->label }}</td>
                    <td class="value">{{ $answersByField[$field->id]?->displayValue() ?: '-' }}</td>
                </tr>
            @endif
        @endforeach
    </table>
</body>
</html>
