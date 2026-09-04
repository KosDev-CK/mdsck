@php $c = $contenido; @endphp

<div>
    <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-1">¿Qué es esta pantalla?</h4>
    <p>{{ $c['concepto'] }}</p>
</div>

<div>
    <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-1">¿Qué resuelve?</h4>
    <p>{{ $c['resuelve'] }}</p>
</div>

@if (! empty($c['proceso']))
    <div>
        <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-1">Proceso de llenado</h4>
        <ol class="list-decimal list-inside space-y-1">
            @foreach ($c['proceso'] as $paso)
                <li>{{ $paso }}</li>
            @endforeach
        </ol>
    </div>
@endif

@if (! empty($c['campos']))
    <div>
        <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Explicación de cada campo</h4>
        <dl class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach ($c['campos'] as $campo)
                <div class="py-2">
                    <dt class="font-medium text-gray-900 dark:text-gray-100">{{ $campo['nombre'] }}</dt>
                    <dd class="text-gray-600 dark:text-gray-400">{{ $campo['explicacion'] }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
@endif
