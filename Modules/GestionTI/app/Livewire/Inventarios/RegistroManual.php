<?php

namespace Modules\GestionTI\Livewire\Inventarios;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\AssetAssignment;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\Marca;
use Modules\GestionTI\Models\Modelo;
use Modules\GestionTI\Models\Propiedad;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Models\Ubicacion;
use Modules\GestionTI\Models\Validador;

/**
 * Registro Manual de Activo (sección 7.12 del spec original) — ver
 * docs/gestionti-progreso.md, Fase 3 etapa 8, para el diseño completo.
 *
 * Sin `edit()` — mismo criterio ya usado repetidamente en este módulo
 * (Recepciones/Asignaciones): un alta manual ya creó un Asset real con
 * `codigo` único asignado; "editarla" implicaría deshacer una alta de ciclo
 * de vida que el spec no describe.
 *
 * `origen_tipo = 'alta_manual'` — deliberadamente distinto de
 * `'ajuste_manual'`, que ya usa `Catalogos/Inventario.php` para un flujo no
 * relacionado (ajuste de catálogo, no alta de activo).
 *
 * No duplica el flujo de responsiva/PDF de `Asignaciones.php`: cuando
 * `destino = 'empleado'`, el `AssetAssignment` que este componente crea
 * aparece también en el listado de esa pantalla (misma tabla
 * `asset_assignments`), donde ya existen "Generar PDF"/"Adjuntar responsiva
 * firmada" — repetir ese flujo aquí sería duplicar superficie sin necesidad.
 */
#[Layout('layouts.app')]
class RegistroManual extends Component
{
    use WithPagination;

    public array $form = [];

    public string $search = '';

    public bool $showModal = false;

    protected function rules(): array
    {
        // Los campos dependientes de `destino = 'empleado'`
        // (empleado_id/estado_equipo_entrega/responsable_entrega_id) no se
        // marcan `required` aquí porque su obligatoriedad depende de otro
        // campo del mismo formulario — se validan aparte en
        // `validateDestino()`, mismo patrón `validateLineas()` ya usado en
        // `Recepciones`/`SolicitudesProveedor`.
        return [
            'form.tipo_equipo_id' => 'required|exists:tipos_equipo,id',
            'form.marca_id' => 'nullable|exists:marcas,id',
            'form.modelo_id' => 'nullable|exists:modelos,id',
            'form.numero_serie' => 'nullable|string|max:255',
            'form.service_tag' => 'nullable|string|max:255',
            'form.costo_adquisicion' => 'nullable|numeric|min:0',
            'form.vendor_id' => 'nullable|exists:proveedores,id',
            'form.fecha_alta_stock' => 'required|date',
            'form.fecha_inicio_garantia' => 'nullable|date',
            'form.fecha_fin_garantia' => 'nullable|date',
            'form.ubicacion_actual_id' => 'required|exists:ubicaciones,id',
            'form.propiedad_id' => 'nullable|exists:propiedades,id',
            'form.dado_de_alta_por_id' => 'required|exists:validadores,id',
            'form.motivo_alta_manual' => 'required|string',
            'form.nota_adquisicion_original' => 'nullable|string',
            'form.destino' => ['required', Rule::in(['stock', 'empleado'])],
            'form.empleado_id' => 'nullable|exists:empleados,id',
            'form.estado_equipo_entrega' => 'nullable|string',
            'form.responsable_entrega_id' => 'nullable|exists:validadores,id',
            'form.accesorios_entregados' => 'nullable|string',
            'form.observaciones' => 'nullable|string',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Ningún select opcional de este formulario manda `''` como "Sin
     * asignar" salvo estos 6 (FKs verdaderamente opcionales) — normalizados
     * aquí antes de validar/guardar, mismo `nullifyEmptyForeignKeys()` ya
     * documentado repetidamente en este módulo.
     */
    private function nullifyEmptyForeignKeys(): void
    {
        foreach (['marca_id', 'modelo_id', 'vendor_id', 'propiedad_id', 'empleado_id', 'responsable_entrega_id'] as $field) {
            if (($this->form[$field] ?? null) === '') {
                $this->form[$field] = null;
            }
        }
    }

    /**
     * Validación cruzada dependiente de `destino` — no expresable con
     * reglas planas de `rules()`, mismo patrón `validateLineas()` ya usado
     * en `Recepciones::validateLineas()`/`SolicitudesProveedor::validateLineas()`.
     */
    private function validateDestino(): void
    {
        if (($this->form['destino'] ?? null) !== 'empleado') {
            return;
        }

        if (empty($this->form['empleado_id'])) {
            $this->addError('form.empleado_id', 'Selecciona el empleado destinatario.');
        }

        if (empty($this->form['estado_equipo_entrega'])
            || ! in_array($this->form['estado_equipo_entrega'], AssetAssignment::ESTADOS_EQUIPO_ENTREGA, true)) {
            $this->addError('form.estado_equipo_entrega', 'Selecciona el estado del equipo entregado.');
        }

        if (empty($this->form['responsable_entrega_id'])) {
            $this->addError('form.responsable_entrega_id', 'Selecciona el responsable de entrega.');
        }
    }

    public function create(): void
    {
        $this->form = [
            'tipo_equipo_id' => null,
            'marca_id' => null,
            'modelo_id' => null,
            'numero_serie' => null,
            'service_tag' => null,
            'costo_adquisicion' => null,
            'vendor_id' => null,
            'fecha_alta_stock' => now()->format('Y-m-d'),
            'fecha_inicio_garantia' => null,
            'fecha_fin_garantia' => null,
            'ubicacion_actual_id' => null,
            'propiedad_id' => null,
            'dado_de_alta_por_id' => null,
            'motivo_alta_manual' => null,
            'nota_adquisicion_original' => null,
            'destino' => 'stock',
            'empleado_id' => null,
            'estado_equipo_entrega' => null,
            'responsable_entrega_id' => null,
            'accesorios_entregados' => null,
            'observaciones' => null,
        ];
        $this->resetValidation();
        $this->showModal = true;
    }

    public function cancel(): void
    {
        $this->showModal = false;
        $this->form = [];
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->nullifyEmptyForeignKeys();
        $this->validate();
        $this->validateDestino();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        // Evita colisión de secuencia en memoria entre tests del mismo
        // proceso PHPUnit — mismo criterio ya usado en `Recepciones::save()`.
        Asset::resetCodigoSequenceCache();

        DB::transaction(function () {
            $tipoEquipo = TipoEquipo::findOrFail($this->form['tipo_equipo_id']);
            $estatusCodigo = $this->form['destino'] === 'empleado' ? 'asignado' : 'en_stock';

            $asset = Asset::create([
                'codigo' => Asset::generateCodigo($tipoEquipo),
                'tipo_equipo_id' => $this->form['tipo_equipo_id'],
                'marca_id' => $this->form['marca_id'],
                'modelo_id' => $this->form['modelo_id'],
                'numero_serie' => $this->form['numero_serie'],
                'service_tag' => $this->form['service_tag'],
                'especificaciones' => null,
                'costo_adquisicion' => $this->form['costo_adquisicion'],
                'origen_tipo' => 'alta_manual',
                'recepcion_linea_id' => null,
                'motivo_alta_manual' => $this->form['motivo_alta_manual'],
                'dado_de_alta_por_id' => $this->form['dado_de_alta_por_id'],
                'vendor_id' => $this->form['vendor_id'],
                'fecha_alta_stock' => $this->form['fecha_alta_stock'],
                'fecha_inicio_garantia' => $this->form['fecha_inicio_garantia'],
                'fecha_fin_garantia' => $this->form['fecha_fin_garantia'],
                'ubicacion_actual_id' => $this->form['ubicacion_actual_id'],
                'sic_reservada_id' => null,
                'proyecto_presupuesto_id' => null,
                'estatus_id' => $this->estatusIdPorCodigo($estatusCodigo),
                'propiedad_id' => $this->form['propiedad_id'],
                'invoice_id' => null,
                'nota_adquisicion_original' => ($this->form['nota_adquisicion_original'] ?? '') !== ''
                    ? $this->form['nota_adquisicion_original']
                    : null,
            ]);

            if ($this->form['destino'] === 'empleado') {
                AssetAssignment::create([
                    'asset_id' => $asset->id,
                    'empleado_id' => $this->form['empleado_id'],
                    'ticket_id' => null,
                    'sic_id' => null,
                    'fecha_asignacion' => $this->form['fecha_alta_stock'],
                    'estado_equipo_entrega' => $this->form['estado_equipo_entrega'],
                    'accesorios_entregados' => ($this->form['accesorios_entregados'] ?? '') !== ''
                        ? $this->form['accesorios_entregados']
                        : null,
                    'responsable_entrega_id' => $this->form['responsable_entrega_id'],
                    'observaciones' => ($this->form['observaciones'] ?? '') !== ''
                        ? $this->form['observaciones']
                        : null,
                ]);
            }
        });

        $this->showModal = false;
        $this->form = [];
        session()->flash('status', 'Activo registrado correctamente.');
    }

    private function estatusIdPorCodigo(string $codigo): int
    {
        $id = EstatusActivo::where('codigo', $codigo)->value('id');

        if ($id === null) {
            throw new \RuntimeException("Falta el estatus base '{$codigo}' en estatus_activo — corre primero `php artisan module:seed GestionTI`.");
        }

        return $id;
    }

    public function render()
    {
        $records = Asset::query()
            ->where('origen_tipo', 'alta_manual')
            ->with(['tipoEquipo', 'marca', 'modelo', 'ubicacionActual', 'estatus', 'dadoDeAltaPor'])
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('codigo', 'like', "%{$this->search}%")
                        ->orWhere('numero_serie', 'like', "%{$this->search}%")
                        ->orWhere('motivo_alta_manual', 'like', "%{$this->search}%");
                });
            })
            ->orderByDesc('fecha_alta_stock')
            ->paginate(10);

        return view('gestionti::livewire.inventarios.registro-manual', [
            'records' => $records,
            'tipoEquipoOptions' => TipoEquipo::where('activo', true)->orderBy('nombre')->get(),
            'marcaOptions' => Marca::where('activo', true)->orderBy('nombre')->get(),
            'modeloOptions' => Modelo::where('activo', true)->orderBy('nombre')->get(),
            'vendorOptions' => Proveedor::where('activo', true)->orderBy('nombre_comercial')->get(),
            'ubicacionOptions' => Ubicacion::where('activo', true)->orderBy('nombre')->get(),
            'propiedadOptions' => Propiedad::where('activo', true)->orderBy('nombre')->get(),
            'validadorOptions' => Validador::where('activo', true)->orderBy('nombre')->get(),
            'empleadoOptions' => Empleado::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }
}
