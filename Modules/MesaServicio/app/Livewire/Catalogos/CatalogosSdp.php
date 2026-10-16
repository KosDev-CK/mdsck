<?php

namespace Modules\MesaServicio\Livewire\Catalogos;

use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\MesaServicio\Models\SdpCatalogEntry;

/**
 * Fase 8 (Parte 2) — pantalla de solo lectura con los 12 catálogos de
 * configuración de SDP (categorías, niveles, modos, prioridades, matriz de
 * prioridades, tipos de solicitud/tarea/bitácora, códigos de cierre, tipos
 * de tiempo de inactividad), organizados en pestañas — un catálogo por
 * pestaña, sin componente de tabs reusable en el repo todavía (ver
 * "Pendiente" en CLAUDE.md), resuelto con una propiedad Livewire simple
 * ($tabActiva) en vez de construir uno nuevo.
 *
 * "Sincronizar catálogos" corre sdp:sync-catalogos (los 12 planos) de forma
 * síncrona dentro del propio request — mismo patrón que
 * Livewire\Dashboard::sincronizar() (Artisan::call + flash + wire:loading).
 */
#[Layout('layouts.app')]
class CatalogosSdp extends Component
{
    use WithPagination;

    private const POR_PAGINA = 25;

    public string $tabActiva = 'categories';

    public bool $sincronizando = false;

    /**
     * Etiquetas en español para cada catálogo (los 14 de
     * SdpCatalogEntry::todos()), usado tanto para las pestañas como para
     * el título de cada tabla.
     *
     * @return array<string, string>
     */
    public static function etiquetas(): array
    {
        return [
            'categories' => 'Categorías',
            'subcategories' => 'Subcategorías',
            'items' => 'Artículos',
            'levels' => 'Niveles',
            'modes' => 'Modos',
            'impacts' => 'Impactos',
            'urgencies' => 'Urgencias',
            'priorities' => 'Prioridades',
            'priority_matrices' => 'Matriz de prioridades',
            'request_types' => 'Tipos de solicitud',
            'task_types' => 'Tipos de tarea',
            'worklog_types' => 'Tipos de bitácora de trabajo',
            'closure_codes' => 'Códigos de cierre',
            'downtime_types' => 'Tipos de tiempo de inactividad',
            'slas' => 'SLAs',
        ];
    }

    /**
     * Minutos -> texto corto ("30 min", "2 h", "1 d 4 h"); "—" si no aplica.
     */
    public static function duracion(?int $minutos): string
    {
        if ($minutos === null) {
            return '—';
        }

        if ($minutos === 0) {
            return 'Sin límite';
        }

        $partes = [];
        foreach ([['d', 1440], ['h', 60], ['min', 1]] as [$unidad, $tamano]) {
            if ($minutos >= $tamano) {
                $partes[] = intdiv($minutos, $tamano).' '.$unidad;
                $minutos %= $tamano;
            }
        }

        return implode(' ', $partes);
    }

    /**
     * Resumen de las 3 banderas "ignora ..." de una regla SLA de SDP.
     */
    public static function calendario(array $extra): string
    {
        $ignora = array_filter([
            ! empty($extra['ignora_fines_de_semana']) ? 'fines de semana' : null,
            ! empty($extra['ignora_feriados']) ? 'feriados' : null,
            ! empty($extra['ignora_horario_operativo']) ? 'horario operativo' : null,
        ]);

        return $ignora === [] ? 'Cuenta todo el tiempo' : 'Ignora '.implode(', ', $ignora);
    }

    public function mount(): void
    {
        // Defensivo: si algún día CATALOGOS cambia de orden/contenido, sigue
        // arrancando en un catálogo válido en vez de una pestaña vacía.
        if (! in_array($this->tabActiva, SdpCatalogEntry::todos(), true)) {
            $this->tabActiva = SdpCatalogEntry::todos()[0];
        }
    }

    public function setTab(string $catalogo): void
    {
        if (in_array($catalogo, SdpCatalogEntry::todos(), true)) {
            $this->tabActiva = $catalogo;
            $this->resetPage();
        }
    }

    public function sincronizar(): void
    {
        $this->sincronizando = true;

        try {
            // Solo los 12 planos: subcategorías y artículos son ~150
            // llamadas anidadas (~95 s), demasiado para un request web
            // síncrono — esos 2 se sincronizan por consola.
            foreach (SdpCatalogEntry::CATALOGOS as $catalogo) {
                if (Artisan::call('sdp:sync-catalogos', ['catalogo' => $catalogo]) !== 0) {
                    throw new \RuntimeException(trim(Artisan::output()));
                }
            }

            session()->flash('status', 'Se sincronizaron los '.count(SdpCatalogEntry::CATALOGOS).' catálogos planos.');
        } catch (\Throwable $e) {
            session()->flash('error', 'No se pudo sincronizar: '.$e->getMessage());
        } finally {
            $this->sincronizando = false;
        }
    }

    public function render()
    {
        return view('mesaservicio::livewire.catalogos.catalogos-sdp', [
            'etiquetas' => self::etiquetas(),
            'esSla' => $this->tabActiva === 'slas',
            'esJerarquico' => in_array($this->tabActiva, SdpCatalogEntry::CATALOGOS_JERARQUICOS, true),
            'entradas' => SdpCatalogEntry::delCatalogo($this->tabActiva)->orderBy('nombre')->orderBy('id')->paginate(self::POR_PAGINA),
        ]);
    }
}
