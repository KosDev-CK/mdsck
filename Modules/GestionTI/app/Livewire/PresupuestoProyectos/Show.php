<?php

namespace Modules\GestionTI\Livewire\PresupuestoProyectos;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\ProyectoPresupuesto;
use Modules\GestionTI\Models\ProyectoPresupuestoArticulo;
use Modules\GestionTI\Models\ProyectoPresupuestoAutorizacion;
use Modules\GestionTI\Models\TipoAviso;
use Modules\GestionTI\Support\Avisos\AvisoDispatcher;

/**
 * Presupuesto por Proyecto — detalle + flujo de trabajo completo (secciones
 * 5.1 y 7.5 del spec original). Ver docs/gestionti-progreso.md para el
 * diseño detallado de las 2 decisiones de arquitectura más importantes:
 *
 * 1) Autorizar el proyecto NO crea automáticamente una `SolicitudProveedor`
 *    (el spec dice literalmente que "dispara" su generación, pero
 *    `solicitudes_proveedor.vendor_id` es requerido y todavía no hay
 *    proveedor cotizado en este punto del flujo) — en vez de eso, los
 *    artículos `laptops_desktops` del proyecto autorizado quedan
 *    disponibles para que Compras los recoja manualmente desde el select
 *    nuevo de la pantalla "Solicitud a Proveedores"
 *    (`Modules\GestionTI\Livewire\Compras\SolicitudesProveedor`).
 * 2) Sin control de acceso por registro — el permiso de pantalla es lo único
 *    que autoriza. `pm_responsable_id`/`responsable_costo_id`/`aprobador_id`
 *    son solo datos descriptivos de quién hace cada acción, no candados de
 *    autorización (no existe relación entre `App\Models\User` y `Empleado`,
 *    mismo criterio que el resto del módulo desde Fase 3 etapa 1).
 *
 * Deferred explícito en esta entrega: edición del encabezado (ver Manage.php),
 * "Enviar por correo" (condicionado a una plantilla corporativa que todavía
 * no existe) y cualquier notificación real (`TipoAviso`/`AvisoEnviado` es
 * Fase 4).
 */
#[Layout('layouts.app')]
class Show extends Component
{
    public ProyectoPresupuesto $proyectoPresupuesto;

    // --- Artículos ---

    public bool $showArticuloModal = false;

    public ?int $editingArticuloId = null;

    public array $articuloForm = [];

    /**
     * Costo unitario editable por artículo (indexado por id de artículo,
     * `wire:model="costoInputs.<id>"`) — inicializado una sola vez en
     * `mount()` (no en `render()`) para no pisar ediciones de una fila
     * cuando una acción sobre OTRA fila dispara un nuevo render.
     *
     * @var array<int, mixed>
     */
    public array $costoInputs = [];

    // --- Autorización ---

    public bool $showAutorizacionModal = false;

    /** @var array<int, array{aprobador_id: ?int}> */
    public array $niveles = [];

    public bool $showResolucionModal = false;

    public ?int $resolviendoId = null;

    public string $resolviendoAccion = '';

    public ?string $resolucionComentario = null;

    public function mount(ProyectoPresupuesto $proyectoPresupuesto): void
    {
        $this->proyectoPresupuesto = $proyectoPresupuesto;
        $this->costoInputs = $proyectoPresupuesto->articulos()->pluck('costo_unitario', 'id')->all();
    }

    // ==================================================================
    // Artículos — solo mientras el proyecto está en `armado`. Una vez que
    // avanza, la composición (categoría/descripción/cantidad/responsable)
    // queda congelada; solo el costo se sigue pudiendo capturar.
    // ==================================================================

    public function openArticuloModal(): void
    {
        if ($this->proyectoPresupuesto->estatus !== ProyectoPresupuesto::ESTATUS_ARMADO) {
            return;
        }

        $this->editingArticuloId = null;
        $this->articuloForm = [
            'categoria' => '',
            'descripcion' => '',
            'cantidad' => 1,
            'responsable_costo_id' => null,
        ];
        $this->resetValidation();
        $this->showArticuloModal = true;
    }

    public function editArticulo(int $id): void
    {
        if ($this->proyectoPresupuesto->estatus !== ProyectoPresupuesto::ESTATUS_ARMADO) {
            return;
        }

        $articulo = $this->proyectoPresupuesto->articulos()->findOrFail($id);

        $this->editingArticuloId = $id;
        $this->articuloForm = [
            'categoria' => $articulo->categoria,
            'descripcion' => $articulo->descripcion,
            'cantidad' => $articulo->cantidad,
            'responsable_costo_id' => $articulo->responsable_costo_id,
        ];
        $this->resetValidation();
        $this->showArticuloModal = true;
    }

    public function cancelArticulo(): void
    {
        $this->showArticuloModal = false;
        $this->editingArticuloId = null;
        $this->articuloForm = [];
        $this->resetValidation();
    }

    public function saveArticulo(): void
    {
        if ($this->proyectoPresupuesto->estatus !== ProyectoPresupuesto::ESTATUS_ARMADO) {
            return;
        }

        $this->validate([
            'articuloForm.categoria' => ['required', Rule::in(ProyectoPresupuestoArticulo::CATEGORIAS)],
            'articuloForm.descripcion' => 'required|string|max:255',
            'articuloForm.cantidad' => 'required|integer|min:1',
            'articuloForm.responsable_costo_id' => 'required|exists:empleados,id',
        ]);

        if ($this->editingArticuloId) {
            $this->proyectoPresupuesto->articulos()->where('id', $this->editingArticuloId)->update($this->articuloForm);
        } else {
            $nuevo = $this->proyectoPresupuesto->articulos()->create($this->articuloForm);
            $this->costoInputs[$nuevo->id] = null;
        }

        $this->showArticuloModal = false;
        session()->flash('status', 'Artículo guardado.');
    }

    public function deleteArticulo(int $id): void
    {
        if ($this->proyectoPresupuesto->estatus !== ProyectoPresupuesto::ESTATUS_ARMADO) {
            return;
        }

        $this->proyectoPresupuesto->articulos()->where('id', $id)->delete();
        unset($this->costoInputs[$id]);
        session()->flash('status', 'Artículo eliminado.');
    }

    /**
     * Captura inline del costo unitario de un artículo — solo mientras el
     * proyecto está `en_captura_costos`. Si con esta captura TODOS los
     * artículos del proyecto quedan `capturado`, el proyecto transiciona
     * automáticamente a `completo`.
     */
    public function capturarCosto(int $articuloId): void
    {
        if ($this->proyectoPresupuesto->estatus !== ProyectoPresupuesto::ESTATUS_EN_CAPTURA_COSTOS) {
            return;
        }

        $articulo = $this->proyectoPresupuesto->articulos()->find($articuloId);

        if (! $articulo) {
            return;
        }

        $this->validate([
            "costoInputs.$articuloId" => 'required|numeric|min:0',
        ]);

        $articulo->update([
            'costo_unitario' => $this->costoInputs[$articuloId],
            'estatus_captura' => ProyectoPresupuestoArticulo::ESTATUS_CAPTURA_CAPTURADO,
            'fecha_captura' => now()->format('Y-m-d'),
        ]);

        $todoCapturado = $this->proyectoPresupuesto->articulos()
            ->where('estatus_captura', ProyectoPresupuestoArticulo::ESTATUS_CAPTURA_PENDIENTE)
            ->doesntExist();

        if ($todoCapturado) {
            $this->proyectoPresupuesto->update(['estatus' => ProyectoPresupuesto::ESTATUS_COMPLETO]);

            app(AvisoDispatcher::class)->disparar(
                TipoAviso::EVENTO_PRESUPUESTO_LISTO_PARA_AUTORIZAR,
                $this->proyectoPresupuesto,
                responsable: $this->proyectoPresupuesto->pmResponsable,
                variables: ['proyecto' => $this->proyectoPresupuesto->nombre_proyecto]
            );
        }

        session()->flash('status', 'Costo capturado.');
    }

    /**
     * `armado` -> `en_captura_costos`. Doble defensa (oculto en la vista +
     * validado aquí) — mismo patrón que el resto de transiciones de estatus
     * de este módulo.
     */
    public function enviarACapturaCostos(): void
    {
        if ($this->proyectoPresupuesto->estatus !== ProyectoPresupuesto::ESTATUS_ARMADO) {
            return;
        }

        if ($this->proyectoPresupuesto->articulos()->doesntExist()) {
            return;
        }

        $this->proyectoPresupuesto->update(['estatus' => ProyectoPresupuesto::ESTATUS_EN_CAPTURA_COSTOS]);
        session()->flash('status', 'Proyecto enviado a captura de costos.');
    }

    // ==================================================================
    // Autorización multi-nivel
    // ==================================================================

    public function openAutorizacionModal(): void
    {
        if ($this->proyectoPresupuesto->estatus !== ProyectoPresupuesto::ESTATUS_COMPLETO) {
            return;
        }

        $this->niveles = [['aprobador_id' => null]];
        $this->resetValidation();
        $this->showAutorizacionModal = true;
    }

    public function addNivel(): void
    {
        $this->niveles[] = ['aprobador_id' => null];
    }

    public function removeNivel(int $index): void
    {
        unset($this->niveles[$index]);
        $this->niveles = array_values($this->niveles);
    }

    public function cancelAutorizacion(): void
    {
        $this->showAutorizacionModal = false;
        $this->niveles = [];
        $this->resetValidation();
    }

    /**
     * `completo` -> crea los niveles de autorización -> `en_autorizacion`.
     */
    public function enviarAAutorizacion(): void
    {
        if ($this->proyectoPresupuesto->estatus !== ProyectoPresupuesto::ESTATUS_COMPLETO) {
            return;
        }

        $this->validate([
            'niveles' => 'required|array|min:1',
            'niveles.*.aprobador_id' => 'required|exists:empleados,id',
        ]);

        DB::transaction(function () {
            foreach ($this->niveles as $index => $nivel) {
                ProyectoPresupuestoAutorizacion::create([
                    'proyecto_id' => $this->proyectoPresupuesto->id,
                    'nivel' => $index + 1,
                    'aprobador_id' => $nivel['aprobador_id'],
                    'estatus' => ProyectoPresupuestoAutorizacion::ESTATUS_PENDIENTE,
                ]);
            }

            $this->proyectoPresupuesto->update(['estatus' => ProyectoPresupuesto::ESTATUS_EN_AUTORIZACION]);
        });

        $this->cancelAutorizacion();
        session()->flash('status', 'Proyecto enviado a autorización.');
    }

    /**
     * Enforcement secuencial real: un nivel solo es accionable si sigue
     * `pendiente` Y ningún nivel anterior (menor) sigue sin `aprobado` —
     * verificado aquí (no solo ocultando el botón en la vista), así que
     * invocar `autorizarNivel()`/`rechazarNivel()` fuera de orden desde
     * Livewire directamente se rechaza en silencio.
     */
    private function esNivelAccionable(ProyectoPresupuestoAutorizacion $autorizacion): bool
    {
        if ($autorizacion->estatus !== ProyectoPresupuestoAutorizacion::ESTATUS_PENDIENTE) {
            return false;
        }

        return ! ProyectoPresupuestoAutorizacion::where('proyecto_id', $autorizacion->proyecto_id)
            ->where('nivel', '<', $autorizacion->nivel)
            ->where('estatus', '!=', ProyectoPresupuestoAutorizacion::ESTATUS_APROBADO)
            ->exists();
    }

    public function openResolucion(int $id, string $accion): void
    {
        $this->resolviendoId = $id;
        $this->resolviendoAccion = $accion;
        $this->resolucionComentario = null;
        $this->resetValidation();
        $this->showResolucionModal = true;
    }

    public function cancelResolucion(): void
    {
        $this->showResolucionModal = false;
        $this->resolviendoId = null;
        $this->resolviendoAccion = '';
        $this->resolucionComentario = null;
        $this->resetValidation();
    }

    public function confirmResolucion(): void
    {
        $this->validate(['resolucionComentario' => 'nullable|string']);

        if ($this->resolviendoAccion === 'aprobar') {
            $this->autorizarNivel((int) $this->resolviendoId, $this->resolucionComentario);
        } elseif ($this->resolviendoAccion === 'rechazar') {
            $this->rechazarNivel((int) $this->resolviendoId, $this->resolucionComentario);
        }

        $this->cancelResolucion();
    }

    /**
     * Si es el nivel accionable, lo marca `aprobado`. Si era el nivel con el
     * número más alto de este proyecto, el proyecto pasa a `autorizado` —
     * único "disparador" real de que sus artículos `laptops_desktops`
     * empiecen a aparecer en el select nuevo de "Solicitud a Proveedores"
     * (ver docs/gestionti-progreso.md).
     */
    public function autorizarNivel(int $id, ?string $comentario = null): void
    {
        $autorizacion = ProyectoPresupuestoAutorizacion::find($id);

        if (! $autorizacion || $autorizacion->proyecto_id !== $this->proyectoPresupuesto->id) {
            return;
        }

        if (! $this->esNivelAccionable($autorizacion)) {
            return;
        }

        $autorizacion->update([
            'estatus' => ProyectoPresupuestoAutorizacion::ESTATUS_APROBADO,
            'fecha_resolucion' => now()->format('Y-m-d'),
            'comentario' => ($comentario ?? '') !== '' ? $comentario : null,
        ]);

        $maxNivel = $this->proyectoPresupuesto->autorizaciones()->max('nivel');

        if ((int) $autorizacion->nivel === (int) $maxNivel) {
            $this->proyectoPresupuesto->update(['estatus' => ProyectoPresupuesto::ESTATUS_AUTORIZADO]);

            $dispatcher = app(AvisoDispatcher::class);

            foreach ($this->proyectoPresupuesto->articulos()->with('responsableCosto')->get()->pluck('responsableCosto')->filter()->unique('id') as $responsable) {
                $dispatcher->disparar(
                    TipoAviso::EVENTO_PROYECTO_AUTORIZADO,
                    $this->proyectoPresupuesto,
                    responsable: $responsable,
                    variables: ['proyecto' => $this->proyectoPresupuesto->nombre_proyecto]
                );
            }
        }

        session()->flash('status', 'Nivel aprobado.');
    }

    /**
     * Si es el nivel accionable, lo marca `rechazado` y el proyecto pasa
     * INMEDIATAMENTE a `rechazado` (estado terminal) — no se tocan los
     * niveles restantes que sigan `pendiente`, quedan tal cual.
     */
    public function rechazarNivel(int $id, ?string $comentario = null): void
    {
        $autorizacion = ProyectoPresupuestoAutorizacion::find($id);

        if (! $autorizacion || $autorizacion->proyecto_id !== $this->proyectoPresupuesto->id) {
            return;
        }

        if (! $this->esNivelAccionable($autorizacion)) {
            return;
        }

        $autorizacion->update([
            'estatus' => ProyectoPresupuestoAutorizacion::ESTATUS_RECHAZADO,
            'fecha_resolucion' => now()->format('Y-m-d'),
            'comentario' => ($comentario ?? '') !== '' ? $comentario : null,
        ]);

        $this->proyectoPresupuesto->update(['estatus' => ProyectoPresupuesto::ESTATUS_RECHAZADO]);

        session()->flash('status', 'Nivel rechazado. El proyecto quedó rechazado.');
    }

    public function render()
    {
        $this->proyectoPresupuesto->load([
            'empresa', 'centroCosto', 'areaOperativa', 'pmResponsable',
            'articulos.responsableCosto',
            'autorizaciones.aprobador',
        ]);

        // No basta con "el primer nivel en estatus pendiente" — si un nivel
        // anterior fue rechazado, el proyecto ya quedó terminal (rechazado)
        // pero los niveles posteriores siguen técnicamente en `pendiente`.
        // Usa la misma regla de `esNivelAccionable()` (exige que TODOS los
        // niveles anteriores estén `aprobado`) para no mostrar botones de
        // Aprobar/Rechazar sobre un nivel que ya no es accionable.
        $nivelAccionable = $this->proyectoPresupuesto->autorizaciones
            ->sortBy('nivel')
            ->first(fn (ProyectoPresupuestoAutorizacion $autorizacion) => $this->esNivelAccionable($autorizacion));

        return view('gestionti::livewire.presupuesto-proyectos.show', [
            'empleadoOptions' => Empleado::where('activo', true)->orderBy('nombre')->get(),
            'nivelAccionableId' => $nivelAccionable?->id,
        ]);
    }
}
