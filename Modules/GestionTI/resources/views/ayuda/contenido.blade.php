{{--
    Contenido de ayuda de una pantalla (cuerpo del modal "?"). Formato del
    archivo `resources/ayuda/data/{slug}.php`:

      titulo, concepto, resuelve                      — siempre
      reglas   => [texto, ...]                        — opcional: reglas del proceso
      proceso  => [texto, ...]                        — opcional: pasos
      campos   => [['nombre', 'explicacion'], ...]    — opcional
      secciones => [                                  — opcional: pantallas grandes
          ['titulo', 'intro'?, 'reglas'?, 'proceso'?, 'campos'?], ...
      ]

    Sin `secciones` se ve de corrido, como siempre. Con `secciones` se arma
    una pestaña "Resumen" (concepto, qué resuelve y las reglas generales) y
    una pestaña por sección — el PDF imprime todo en orden (ver
    `pdf/ayuda.blade.php`).
--}}
@php
    $c = $contenido;
    $secciones = $c['secciones'] ?? [];
@endphp

<div x-data="{ tab: 0 }" class="space-y-5">
    @if (! empty($secciones))
        <div class="flex flex-wrap gap-1 border-b border-gray-200 dark:border-gray-800">
            <button type="button" x-on:click="tab = 0" x-bind:class="tab === 0 ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="-mb-px border-b-2 px-3 py-1.5 text-sm font-medium">Resumen</button>
            @foreach ($secciones as $i => $seccion)
                <button type="button" x-on:click="tab = {{ $i + 1 }}" x-bind:class="tab === {{ $i + 1 }} ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="-mb-px border-b-2 px-3 py-1.5 text-sm font-medium">{{ $seccion['titulo'] }}</button>
            @endforeach
        </div>
    @endif

    <div class="space-y-5" @if (! empty($secciones)) x-show="tab === 0" @endif>
        <div>
            <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-1">¿Qué es esta pantalla?</h4>
            <p>{{ $c['concepto'] }}</p>
        </div>

        <div>
            <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-1">¿Qué resuelve?</h4>
            <p>{{ $c['resuelve'] }}</p>
        </div>

        @if (! empty($c['reglas']))
            <div>
                <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-1">Reglas del proceso</h4>
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($c['reglas'] as $regla)
                        <li>{{ $regla }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

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
    </div>

    @foreach ($secciones as $i => $seccion)
        <div class="space-y-5" x-show="tab === {{ $i + 1 }}" x-cloak>
            @if (! empty($seccion['intro']))
                <p>{{ $seccion['intro'] }}</p>
            @endif

            @if (! empty($seccion['reglas']))
                <div>
                    <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-1">Reglas</h4>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($seccion['reglas'] as $regla)
                            <li>{{ $regla }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! empty($seccion['proceso']))
                <div>
                    <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-1">Paso a paso</h4>
                    <ol class="list-decimal list-inside space-y-1">
                        @foreach ($seccion['proceso'] as $paso)
                            <li>{{ $paso }}</li>
                        @endforeach
                    </ol>
                </div>
            @endif

            @if (! empty($seccion['campos']))
                <div>
                    <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Explicación de cada campo</h4>
                    <dl class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($seccion['campos'] as $campo)
                            <div class="py-2">
                                <dt class="font-medium text-gray-900 dark:text-gray-100">{{ $campo['nombre'] }}</dt>
                                <dd class="text-gray-600 dark:text-gray-400">{{ $campo['explicacion'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif
        </div>
    @endforeach
</div>
