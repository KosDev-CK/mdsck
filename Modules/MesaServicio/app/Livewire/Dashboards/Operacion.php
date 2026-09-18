<?php

namespace Modules\MesaServicio\Livewire\Dashboards;

use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Dashboard de Operación — el día a día operativo del equipo de mesa de
 * servicio. Similar en espíritu al Dashboard original
 * (`Modules\MesaServicio\Livewire\Dashboard`), pero es una pantalla nueva e
 * independiente, no una copia de su código — ese dashboard original se
 * queda tal cual, sin tocarse.
 *
 * Scaffolding únicamente por ahora — el contenido real se construye en una
 * iteración futura. Ver
 * Modules/MesaServicio/resources/ayuda/data/dashboard-operacion.php para el
 * texto de ayuda.
 */
#[Layout('layouts.app')]
class Operacion extends Component
{
    public function render()
    {
        return view('mesaservicio::livewire.dashboards.operacion');
    }
}
