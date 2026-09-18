<?php

namespace Modules\MesaServicio\Livewire\Dashboards;

use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Dashboard Ejecutivo — pensado para dirección: resumen de alto nivel,
 * informes y avances (el "qué" resumido, sin el detalle operativo del día a
 * día). Pantalla nueva e independiente del Dashboard operativo original
 * (`Modules\MesaServicio\Livewire\Dashboard`, que se queda tal cual).
 *
 * Scaffolding únicamente por ahora — el contenido real (KPIs, tendencias)
 * se construye en una iteración futura. Ver
 * Modules/MesaServicio/resources/ayuda/data/dashboard-ejecutivo.php para el
 * texto de ayuda.
 */
#[Layout('layouts.app')]
class Ejecutivo extends Component
{
    public function render()
    {
        return view('mesaservicio::livewire.dashboards.ejecutivo');
    }
}
