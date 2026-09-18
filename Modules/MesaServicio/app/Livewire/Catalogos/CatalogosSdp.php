<?php

namespace Modules\MesaServicio\Livewire\Catalogos;

use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Layout;
use Livewire\Component;
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
 * "Sincronizar catálogos" corre sdp:sync-catalogos (los 12) de forma
 * síncrona dentro del propio request — mismo patrón que
 * Livewire\Dashboard::sincronizar() (Artisan::call + flash + wire:loading).
 */
#[Layout('layouts.app')]
class CatalogosSdp extends Component
{
    public string $tabActiva = 'categories';

    public bool $sincronizando = false;

    /**
     * Etiquetas en español para cada catálogo — mismo orden que
     * SdpCatalogEntry::CATALOGOS, usado tanto para las pestañas como para
     * el título de cada tabla.
     *
     * @return array<string, string>
     */
    public static function etiquetas(): array
    {
        return [
            'categories' => 'Categorías',
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
        ];
    }

    public function mount(): void
    {
        // Defensivo: si algún día CATALOGOS cambia de orden/contenido, sigue
        // arrancando en un catálogo válido en vez de una pestaña vacía.
        if (! in_array($this->tabActiva, SdpCatalogEntry::CATALOGOS, true)) {
            $this->tabActiva = SdpCatalogEntry::CATALOGOS[0];
        }
    }

    public function setTab(string $catalogo): void
    {
        if (in_array($catalogo, SdpCatalogEntry::CATALOGOS, true)) {
            $this->tabActiva = $catalogo;
        }
    }

    public function sincronizar(): void
    {
        $this->sincronizando = true;

        try {
            Artisan::call('sdp:sync-catalogos');

            session()->flash('status', trim(Artisan::output()) ?: 'Sincronización completada.');
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
            'entradas' => SdpCatalogEntry::delCatalogo($this->tabActiva)->orderBy('nombre')->get(),
        ]);
    }
}
