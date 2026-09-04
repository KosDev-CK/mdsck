<div>
    @push('page-title')
        Asignación de Activo
    @endpush

    @push('page-actions')
        <x-ui.help-button />
    @endpush

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @php
        $estadoLabels = [
            'nuevo' => 'Nuevo',
            'usado' => 'Usado',
            'reacondicionado' => 'Reacondicionado',
        ];
    @endphp

    <x-ui.card padding="p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <input
                wire:model.live.debounce.300ms="search"
                type="search"
                placeholder="Buscar por código de activo, empleado o folio SIC..."
                class="w-full sm:w-80 rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
            >
            <x-ui.button wire:click="create">Nuevo</x-ui.button>
        </div>

        <x-ui.table :headers="['Activo', 'Empleado', 'SIC', 'Fecha de asignación', 'Estado', 'Responsable de entrega', '']" :empty="$records->isEmpty()" empty-description="Registra la primera asignación con el botón Nuevo.">
            @foreach ($records as $record)
                <tr wire:key="asignacion-{{ $record->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">
                        {{ $record->asset?->codigo }}
                        @if ($record->asset)
                            <a href="{{ route('gestionti.ficha-activo.show', $record->asset_id) }}" class="ml-2 text-xs font-normal text-primary hover:brightness-90">Ver ficha</a>
                        @endif
                    </td>
                    <td class="py-2">{{ $record->empleado?->nombre }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->sic?->folio_sic ?? ($record->sic ? 'Sin folio' : '—') }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ optional($record->fecha_asignacion)->format('d/m/Y') ?? $record->fecha_asignacion }}</td>
                    <td class="py-2">
                        <x-ui.badge :color="$record->fecha_devolucion ? 'gray' : 'emerald'">{{ $record->fecha_devolucion ? 'Devuelta' : 'Activa' }}</x-ui.badge>
                    </td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->responsableEntrega?->nombre }}</td>
                    <td class="py-2 text-right space-x-2 whitespace-nowrap">
                        <button wire:click="exportResponsivaPdf({{ $record->id }})" class="text-sm text-primary hover:brightness-90">Generar PDF</button>

                        @if (! $record->documento_responsiva_id)
                            <button wire:click="openAttach({{ $record->id }})" class="text-indigo-600 hover:text-indigo-500 text-sm dark:text-indigo-400 dark:hover:text-indigo-300">
                                Adjuntar responsiva firmada
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div class="mt-4">{{ $records->links() }}</div>
    </x-ui.card>

    <x-ui.modal model="showModal" title="Nueva asignación de activo" max-width="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <x-ui.select label="SIC autorizada pendiente" name="form.sic_id" wire:model.live="form.sic_id">
                <option value="">Selecciona...</option>
                @foreach ($sicOptions as $sic)
                    <option value="{{ $sic->id }}">{{ $this->sicOptionLabel($sic) }}</option>
                @endforeach
            </x-ui.select>

            @if ($form['sic_id'] ?? null)
                <div class="rounded-md bg-gray-50 dark:bg-gray-800/50 p-3 text-sm">
                    <span class="font-medium text-gray-700 dark:text-gray-300">Empleado destinatario:</span>
                    {{ $selectedSic?->empleado?->nombre ?? '—' }}
                </div>
            @endif

            <x-ui.select label="Activo" name="form.asset_id" wire:model="form.asset_id">
                <option value="">Selecciona...</option>
                @foreach ($assetOptions as $asset)
                    <option value="{{ $asset->id }}">{{ $this->assetOptionLabel($asset) }}</option>
                @endforeach
            </x-ui.select>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.input label="Fecha de asignación" name="form.fecha_asignacion" type="date" wire:model="form.fecha_asignacion" />

                <x-ui.select label="Estado del equipo entregado" name="form.estado_equipo_entrega" wire:model="form.estado_equipo_entrega">
                    <option value="">Selecciona...</option>
                    @foreach ($estadoLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <x-ui.input label="Accesorios entregados (opcional)" name="form.accesorios_entregados" type="textarea" wire:model="form.accesorios_entregados" />

            <x-ui.select label="Responsable de entrega" name="form.responsable_entrega_id" wire:model="form.responsable_entrega_id">
                <option value="">Selecciona...</option>
                @foreach ($validadorOptions as $validador)
                    <option value="{{ $validador->id }}">{{ $validador->nombre }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.input label="Observaciones (opcional)" name="form.observaciones" type="textarea" wire:model="form.observaciones" />

            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Configuración técnica (opcional)</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                    No todos los tipos de equipo tienen esta información (ej. un Access Point) — deja en blanco lo que no aplique.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.input label="IP" name="form.ip" wire:model="form.ip" />
                    <x-ui.input label="MAC Wi-Fi" name="form.mac_wifi" wire:model="form.mac_wifi" />
                    <x-ui.input label="MAC Ethernet" name="form.mac_ethernet" wire:model="form.mac_ethernet" />

                    <x-ui.select label="Sistema operativo" name="form.sistema_operativo_id" wire:model="form.sistema_operativo_id">
                        <option value="">Sin asignar</option>
                        @foreach ($sistemaOperativoOptions as $so)
                            <option value="{{ $so->id }}">{{ $so->nombre }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.input label="Versión de Office" name="form.version_office" wire:model="form.version_office" />
                    <x-ui.input label="ID de producto del S.O." name="form.id_producto_so" wire:model="form.id_producto_so" />
                    <x-ui.input label="Antivirus" name="form.antivirus" wire:model="form.antivirus" />
                    <x-ui.input label="Dominio" name="form.dominio" wire:model="form.dominio" />
                    <x-ui.input label="Usuario de dominio" name="form.usuario_dominio" wire:model="form.usuario_dominio" />

                    <x-ui.select label="Libra Cloud" name="form.libra_cloud" wire:model="form.libra_cloud">
                        <option value="">Sin capturar</option>
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </x-ui.select>

                    <x-ui.select label="Oracle/EBS" name="form.oracle_ebs" wire:model="form.oracle_ebs">
                        <option value="">Sin capturar</option>
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </x-ui.select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Documento firmado (opcional)</label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                    Puede adjuntarse ahora o después desde el listado, una vez impresa y firmada la responsiva.
                </p>

                @if ($documentoResponsivaVinculado)
                    <div class="flex items-center justify-between rounded-md bg-gray-50 dark:bg-gray-800/50 p-2 text-sm">
                        <span class="text-gray-700 dark:text-gray-300">Vinculado de SharePoint: {{ $documentoResponsivaVinculado['nombre'] }}</span>
                        <button type="button" wire:click="$set('documentoResponsivaVinculado', null)" class="text-xs text-red-600 hover:text-red-500 dark:text-red-400">Quitar</button>
                    </div>
                @else
                    <input wire:model="documentoResponsiva" type="file" accept="image/*,.pdf" class="mt-1 block w-full text-sm text-gray-600 dark:text-gray-300">
                    <div wire:loading wire:target="documentoResponsiva" class="text-xs text-gray-500 dark:text-gray-400 mt-1">Subiendo...</div>
                    <button type="button" wire:click="openSharePointBuscar('documentoResponsiva')" class="mt-1 text-xs text-primary hover:underline">Buscar en SharePoint</button>
                @endif

                @error('documentoResponsiva')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancel">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal model="showAttachModal" title="Adjuntar responsiva firmada">
        <form wire:submit="confirmAttach" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Documento firmado</label>

                @if ($attachDocumentoVinculado)
                    <div class="flex items-center justify-between rounded-md bg-gray-50 dark:bg-gray-800/50 p-2 text-sm">
                        <span class="text-gray-700 dark:text-gray-300">Vinculado de SharePoint: {{ $attachDocumentoVinculado['nombre'] }}</span>
                        <button type="button" wire:click="$set('attachDocumentoVinculado', null)" class="text-xs text-red-600 hover:text-red-500 dark:text-red-400">Quitar</button>
                    </div>
                @else
                    <input wire:model="attachDocumento" type="file" accept="image/*,.pdf" class="mt-1 block w-full text-sm text-gray-600 dark:text-gray-300">
                    <div wire:loading wire:target="attachDocumento" class="text-xs text-gray-500 dark:text-gray-400 mt-1">Subiendo...</div>
                    <button type="button" wire:click="openSharePointBuscar('attachDocumento')" class="mt-1 text-xs text-primary hover:underline">Buscar en SharePoint</button>
                @endif

                @error('attachDocumento')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancelAttach">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal model="showSharePointModal" title="Buscar en SharePoint">
        <div class="space-y-4">
            <input
                wire:model.live.debounce.300ms="sharePointSearch"
                type="search"
                placeholder="Buscar por nombre de archivo..."
                class="w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
            >

            @error('sharePointArchivos')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            <div class="max-h-64 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($sharePointArchivosFiltrados as $archivo)
                    <button
                        type="button"
                        wire:click="elegirArchivoSharePoint('{{ $archivo['driveItemId'] }}')"
                        class="flex w-full items-center justify-between py-2 text-left text-sm text-gray-700 hover:text-primary dark:text-gray-300"
                    >
                        {{ $archivo['nombre'] }}
                    </button>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400 py-2">Sin archivos en esta carpeta.</p>
                @endforelse
            </div>

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancelSharePointBuscar">Cancelar</x-ui.button>
            </div>
        </div>
    </x-ui.modal>

    <x-ui.help-modal titulo="Asignación de Activo" :pdf-url="route('gestionti.ayuda.pdf', 'asignaciones')">
        @include('gestionti::ayuda.contenido', ['contenido' => \Modules\GestionTI\Support\Ayuda\AyudaCatalog::contenido('asignaciones')])
    </x-ui.help-modal>
</div>
