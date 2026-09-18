<?php

namespace Modules\MesaServicio\Livewire\Dashboards;

use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Dashboard Analítico — análisis más profundo: patrones, tendencias
 * históricas, comparativas (el "por qué" detrás de los números, a
 * diferencia del Dashboard Ejecutivo que es el "qué" resumido). Pantalla
 * nueva e independiente del Dashboard operativo original
 * (`Modules\MesaServicio\Livewire\Dashboard`, que se queda tal cual).
 *
 * Scaffolding únicamente por ahora — el contenido real se construye en una
 * iteración futura. Ver
 * Modules/MesaServicio/resources/ayuda/data/dashboard-analitico.php para el
 * texto de ayuda.
 */
#[Layout('layouts.app')]
class Analitico extends Component
{
    public function render()
    {
        return view('mesaservicio::livewire.dashboards.analitico');
    }
}
