<div>
    @push('page-title')
        Presupuesto por Proyecto — {{ $proyectoPresupuesto->nombre_proyecto }}
    @endpush

    @push('page-actions')
        <x-ui.help-button />
    @endpush

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @php
        $estatusLabels = [
            'armado' => 'Armado',
            'en_captura_costos' => 'En captura de costos',
            'completo' => 'Completo',
            'en_autorizacion' => 'En autorización',
            'autorizado' => 'Autorizado',
            'rechazado' => 'Rechazado',
        ];
        $estatusColors = [
            'armado' => 'gray',
            'en_captura_costos' => 'amber',
            'completo' => 'indigo',
            'en_autorizacion' => 'indigo',
            'autorizado' => 'emerald',
            'rechazado' => 'red',
        ];
        $categoriaLabels = \Modules\GestionTI\Models\ProyectoPresupuestoArticulo::CATEGORIA_LABELS;
        $capturaLabels = ['pendiente' => 'Pendiente', 'capturado' => 'Capturado'];
        $capturaColors = ['pendiente' => 'amber', 'capturado' => 'emerald'];
        $autorizacionLabels = ['pendiente' => 'Pendiente', 'aprobado' => 'Aprobado', 'rechazado' => 'Rechazado'];
        $autorizacionColors = ['pendiente' => 'amber', 'aprobado' => 'emerald', 'rechazado' => 'red'];
    @endphp

    <div class="flex items-center justify-between gap-3 mb-4">
        <a href="{{ route('gestionti.presupuestos-proyecto.index') }}" class="text-sm text-primary hover:brightness-90">&larr; Volver al listado</a>

        <a href="{{ route('gestionti.presupuestos-proyecto.export', $proyectoPresupuesto) }}">
            <x-ui.button type="button" variant="secondary">Exportar a Excel</x-ui.button>
        </a>
    </div>

    <x-ui.card padding="p-5" class="mb-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Encabezado</h3>
            <x-ui.badge :color="$estatusColors[$proyectoPresupuesto->estatus] ?? 'gray'">{{ $estatusLabels[$proyectoPresupuesto->estatus] ?? $proyectoPresupuesto->estatus }}</x-ui.badge>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Nombre del proyecto</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $proyectoPresupuesto->nombre_proyecto }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Empresa</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $proyectoPresupuesto->empresa?->nombre_comercial }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Centro de costo</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $proyectoPresupuesto->centroCosto?->nombre }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Dirección del centro</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $proyectoPresupuesto->direccion_centro }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Área operativa solicitante</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $proyectoPresupuesto->areaOperativa?->nombre }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">PM responsable</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $proyectoPresupuesto->pmResponsable?->nombre }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Fecha de solicitud</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $proyectoPresupuesto->fecha_solicitud?->format('d/m/Y') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Fecha límite de captura</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $proyectoPresupuesto->fecha_limite_captura?->format('d/m/Y') }}</dd>
            </div>
        </dl>
    </x-ui.card>

    <x-ui.card padding="p-5" class="mb-4">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Artículos</h3>

            <div class="flex items-center gap-2">
                @if ($proyectoPresupuesto->estatus === 'armado')
                    <x-ui.button type="button" wire:click="openArticuloModal">Agregar artículo</x-ui.button>

                    @if ($proyectoPresupuesto->articulos->isNotEmpty())
                        <x-ui.button
                            type="button"
                            variant="secondary"
                            wire:click="enviarACapturaCostos"
                            wire:confirm="¿Enviar este proyecto a captura de costos? La lista de artículos quedará congelada."
                        >
                            Enviar a captura de costos
                        </x-ui.button>
                    @endif
                @endif

                @if ($proyectoPresupuesto->estatus === 'completo')
                    <x-ui.button type="button" wire:click="openAutorizacionModal">Enviar a autorización</x-ui.button>
                @endif
            </div>
        </div>

        <x-ui.table :headers="['Categoría', 'Descripción', 'Cantidad', 'Responsable de costo', 'Costo unitario', 'Estatus de captura', '']" :empty="$proyectoPresupuesto->articulos->isEmpty()" empty-description="Agrega el primero con el botón Agregar artículo.">
            @foreach ($proyectoPresupuesto->articulos as $articulo)
                <tr wire:key="articulo-{{ $articulo->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $categoriaLabels[$articulo->categoria] ?? $articulo->categoria }}</td>
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $articulo->descripcion }}</td>
                    <td class="py-2">{{ $articulo->cantidad }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $articulo->responsableCosto?->nombre }}</td>
                    <td class="py-2">
                        @if ($proyectoPresupuesto->estatus === 'en_captura_costos' && $articulo->estatus_captura === 'pendiente')
                            <div class="flex items-center gap-2">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    wire:model="costoInputs.{{ $articulo->id }}"
                                    class="w-28 rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
                                >
                                <button type="button" wire:click="capturarCosto({{ $articulo->id }})" class="text-sm text-primary hover:brightness-90">
                                    Guardar costo
                                </button>
                            </div>
                            @error("costoInputs.{$articulo->id}")
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        @elseif ($articulo->costo_unitario !== null)
                            ${{ number_format($articulo->costo_unitario, 2) }}
                        @else
                            <x-ui.badge color="amber">Pendiente</x-ui.badge>
                        @endif
                    </td>
                    <td class="py-2">
                        <x-ui.badge :color="$capturaColors[$articulo->estatus_captura] ?? 'gray'">{{ $capturaLabels[$articulo->estatus_captura] ?? $articulo->estatus_captura }}</x-ui.badge>
                    </td>
                    <td class="py-2 text-right space-x-2 whitespace-nowrap">
                        @if ($proyectoPresupuesto->estatus === 'armado')
                            <button wire:click="editArticulo({{ $articulo->id }})" class="text-indigo-600 hover:text-indigo-500 text-sm dark:text-indigo-400 dark:hover:text-indigo-300">Editar</button>
                            <button
                                wire:click="deleteArticulo({{ $articulo->id }})"
                                wire:confirm="¿Eliminar este artículo?"
                                class="text-sm text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300"
                            >
                                Eliminar
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    @if (in_array($proyectoPresupuesto->estatus, ['en_autorizacion', 'autorizado', 'rechazado']))
        <x-ui.card padding="p-5">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Autorización</h3>

            <x-ui.table :headers="['Nivel', 'Aprobador', 'Estatus', 'Fecha de resolución', 'Comentario', '']" :empty="$proyectoPresupuesto->autorizaciones->isEmpty()">
                @foreach ($proyectoPresupuesto->autorizaciones->sortBy('nivel') as $autorizacion)
                    <tr wire:key="autorizacion-{{ $autorizacion->id }}" class="border-b border-gray-50 dark:border-gray-800">
                        <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $autorizacion->nivel }}</td>
                        <td class="py-2">{{ $autorizacion->aprobador?->nombre }}</td>
                        <td class="py-2">
                            <x-ui.badge :color="$autorizacionColors[$autorizacion->estatus] ?? 'gray'">{{ $autorizacionLabels[$autorizacion->estatus] ?? $autorizacion->estatus }}</x-ui.badge>
                        </td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $autorizacion->fecha_resolucion?->format('d/m/Y') ?? '—' }}</td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $autorizacion->comentario ?? '—' }}</td>
                        <td class="py-2 text-right space-x-2 whitespace-nowrap">
                            @if ($autorizacion->id === $nivelAccionableId)
                                <button wire:click="openResolucion({{ $autorizacion->id }}, 'aprobar')" class="text-sm text-emerald-600 hover:text-emerald-500 dark:text-emerald-400 dark:hover:text-emerald-300">
                                    Aprobar
                                </button>
                                <button wire:click="openResolucion({{ $autorizacion->id }}, 'rechazar')" class="text-sm text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300">
                                    Rechazar
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif

    <x-ui.modal model="showArticuloModal" :title="($editingArticuloId ? 'Editar' : 'Nuevo') . ' — Artículo'">
        <form wire:submit="saveArticulo" class="space-y-4">
            <x-ui.select label="Categoría" name="articuloForm.categoria" wire:model="articuloForm.categoria">
                <option value="">Selecciona...</option>
                @foreach (\Modules\GestionTI\Models\ProyectoPresupuestoArticulo::CATEGORIA_LABELS as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.input label="Descripción" name="articuloForm.descripcion" wire:model="articuloForm.descripcion" />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.input label="Cantidad" name="articuloForm.cantidad" type="number" wire:model="articuloForm.cantidad" />

                <x-ui.select label="Responsable de costo" name="articuloForm.responsable_costo_id" wire:model="articuloForm.responsable_costo_id">
                    <option value="">Selecciona...</option>
                    @foreach ($empleadoOptions as $empleado)
                        <option value="{{ $empleado->id }}">{{ $empleado->nombre }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancelArticulo">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal model="showAutorizacionModal" title="Enviar a autorización" max-width="max-w-2xl">
        <form wire:submit="enviarAAutorizacion" class="space-y-4">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Niveles de autorización</label>
                    <button type="button" wire:click="addNivel" class="text-sm text-primary hover:underline">+ Agregar nivel</button>
                </div>

                @error('niveles')
                    <p class="mb-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <div class="space-y-3">
                    @foreach ($niveles as $i => $nivel)
                        <div wire:key="nivel-{{ $i }}" class="flex items-end gap-2">
                            <div class="flex-1">
                                <x-ui.select label="Nivel {{ $i + 1 }} — Aprobador" name="niveles.{{ $i }}.aprobador_id" wire:model="niveles.{{ $i }}.aprobador_id">
                                    <option value="">Selecciona...</option>
                                    @foreach ($empleadoOptions as $empleado)
                                        <option value="{{ $empleado->id }}">{{ $empleado->nombre }}</option>
                                    @endforeach
                                </x-ui.select>
                            </div>

                            <button type="button" wire:click="removeNivel({{ $i }})" class="text-sm text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300 mb-2">
                                Quitar
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancelAutorizacion">Cancelar</x-ui.button>
                <x-ui.button type="submit">Enviar a autorización</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal model="showResolucionModal" :title="$resolviendoAccion === 'aprobar' ? 'Aprobar nivel' : 'Rechazar nivel'">
        <form wire:submit="confirmResolucion" class="space-y-4">
            <x-ui.input label="Comentario (opcional)" name="resolucionComentario" type="textarea" wire:model="resolucionComentario" />

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancelResolucion">Cancelar</x-ui.button>
                <x-ui.button type="submit" wire:confirm="¿Confirmar esta resolución?">Confirmar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.help-modal titulo="Presupuesto por Proyecto" :pdf-url="route('gestionti.ayuda.pdf', 'presupuestos-proyecto')">
        @include('gestionti::ayuda.contenido', ['contenido' => \Modules\GestionTI\Support\Ayuda\AyudaCatalog::contenido('presupuestos-proyecto')])
    </x-ui.help-modal>
</div>
