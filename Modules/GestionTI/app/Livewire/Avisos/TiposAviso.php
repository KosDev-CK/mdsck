<?php

namespace Modules\GestionTI\Livewire\Avisos;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\GestionTI\Models\TipoAviso;
use Modules\GestionTI\Models\TipoAvisoDestinatario;
use Modules\GestionTI\Models\Validador;
use Spatie\Permission\Models\Role;

/**
 * Catálogo de configuración "Configuración de Avisos" (sección 7.15 del spec
 * original). A diferencia del resto de pantallas de ciclo de vida de este
 * módulo, SÍ lleva `edit()` sobre un registro ya sembrado — es un catálogo de
 * configuración pura, sin historial que proteger.
 *
 * El repetidor de `destinatarios` sigue el mismo patrón `addX/removeX` ya
 * usado en `PresupuestoProyectos\Show::addNivel()/removeNivel()`. Al guardar
 * se sincroniza vía delete+create (más simple que diffear, mismo criterio ya
 * documentado para "Fusionar duplicados").
 */
#[Layout('layouts.app')]
class TiposAviso extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    public array $form = [];

    /** @var array<int, array{tipo_destinatario: ?string, rol_nombre: ?string, validador_id: ?int}> */
    public array $destinatarios = [];

    #[Url(as: 'search')]
    public string $search = '';

    public bool $showModal = false;

    protected function rules(): array
    {
        return [
            'form.codigo' => ['required', 'string', 'max:100', Rule::unique('tipos_aviso', 'codigo')->ignore($this->editingId)],
            'form.descripcion' => 'required|string|max:255',
            'form.entidad_relacionada' => 'required|string|max:255',
            'form.evento_disparador' => ['required', 'string', 'max:100', Rule::unique('tipos_aviso', 'evento_disparador')->ignore($this->editingId)],
            'form.plantilla_mensaje' => 'required|string',
            'form.activo' => 'boolean',
            'destinatarios' => 'array',
            'destinatarios.*.tipo_destinatario' => ['required', Rule::in(TipoAvisoDestinatario::TIPOS)],
            'destinatarios.*.rol_nombre' => 'nullable|string|max:255',
            'destinatarios.*.validador_id' => 'nullable|exists:validadores,id',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->editingId = null;
        $this->form = [
            'codigo' => null,
            'descripcion' => null,
            'entidad_relacionada' => null,
            'evento_disparador' => null,
            'plantilla_mensaje' => null,
            'activo' => true,
        ];
        $this->destinatarios = [];
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $record = TipoAviso::with('destinatarios')->findOrFail($id);

        $this->editingId = $id;
        $this->form = [
            'codigo' => $record->codigo,
            'descripcion' => $record->descripcion,
            'entidad_relacionada' => $record->entidad_relacionada,
            'evento_disparador' => $record->evento_disparador,
            'plantilla_mensaje' => $record->plantilla_mensaje,
            'activo' => $record->activo,
        ];
        $this->destinatarios = $record->destinatarios->map(fn (TipoAvisoDestinatario $d) => [
            'tipo_destinatario' => $d->tipo_destinatario,
            'rol_nombre' => $d->rol_nombre,
            'validador_id' => $d->validador_id,
        ])->all();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function addDestinatario(): void
    {
        $this->destinatarios[] = ['tipo_destinatario' => '', 'rol_nombre' => null, 'validador_id' => null];
    }

    public function removeDestinatario(int $index): void
    {
        unset($this->destinatarios[$index]);
        $this->destinatarios = array_values($this->destinatarios);
    }

    /**
     * Los selects condicionales de rol/validador mandan '' cuando no aplican
     * al tipo de destinatario elegido (o cuando se deja "Sin seleccionar") —
     * normaliza antes de guardar, mismo bug/fix ya documentado repetidas
     * veces en este módulo desde Fase 1 (`nullifyEmptyForeignKeys()`).
     */
    private function nullifyEmptyForeignKeys(): void
    {
        foreach ($this->destinatarios as $i => $destinatario) {
            if (($destinatario['validador_id'] ?? null) === '') {
                $this->destinatarios[$i]['validador_id'] = null;
            }

            if (($destinatario['rol_nombre'] ?? null) === '') {
                $this->destinatarios[$i]['rol_nombre'] = null;
            }
        }
    }

    public function save(): void
    {
        $this->nullifyEmptyForeignKeys();
        $this->validate();

        DB::transaction(function () {
            if ($this->editingId) {
                $tipoAviso = TipoAviso::findOrFail($this->editingId);
                $tipoAviso->update($this->form);
            } else {
                $tipoAviso = TipoAviso::create($this->form);
            }

            // Sincroniza destinatarios: delete + create, más simple que
            // diffear — mismo criterio ya usado en "Fusionar duplicados".
            $tipoAviso->destinatarios()->delete();

            foreach ($this->destinatarios as $destinatario) {
                $tipoAviso->destinatarios()->create($destinatario);
            }
        });

        $this->showModal = false;
        session()->flash('status', 'Guardado correctamente.');
    }

    public function toggleActivo(int $id): void
    {
        $record = TipoAviso::findOrFail($id);
        $record->update(['activo' => ! $record->activo]);
    }

    public function cancel(): void
    {
        $this->showModal = false;
        $this->editingId = null;
        $this->form = [];
        $this->destinatarios = [];
        $this->resetValidation();
    }

    public function render()
    {
        $records = TipoAviso::query()
            ->withCount('destinatarios')
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('codigo', 'like', "%{$this->search}%")
                        ->orWhere('descripcion', 'like', "%{$this->search}%")
                        ->orWhere('evento_disparador', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('codigo')
            ->paginate(10);

        return view('gestionti::livewire.avisos.tipos-aviso', [
            'records' => $records,
            'rolOptions' => Role::pluck('name'),
            'validadorOptions' => Validador::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }
}
