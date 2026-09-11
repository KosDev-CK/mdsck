<?php

namespace Modules\MesaServicio\Livewire\Reportes;

use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\MesaServicio\Models\SdpReport;

/**
 * Historial de cierres (Fase 4) — solo lectura + descarga. La generación
 * real de cada reporte la hace Modules\MesaServicio\Console\Commands\DailyCloseCommand
 * (vía el scheduler), esta pantalla no dispara cierres manuales.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    /**
     * Lee el archivo ya guardado en el disco privado y lo transmite tal cual
     * — no regenera el Excel al vuelo (a diferencia de
     * Modules\FormBuilder\Livewire\Links\Show::exportPdf(), que sí genera el
     * PDF en el momento). Se usa Storage::get() + streamDownload() en vez de
     * Storage::download() porque este último no acepta un nombre de archivo
     * de descarga distinto al basename real cuando el disco no expone URL
     * pública (disco 'local' privado) sin pasos extra.
     */
    public function download(int $reportId)
    {
        $report = SdpReport::findOrFail($reportId);

        abort_unless(Storage::disk('local')->exists($report->ruta_archivo), 404);

        return response()->streamDownload(
            fn () => print Storage::disk('local')->get($report->ruta_archivo),
            $report->downloadFilename()
        );
    }

    public function render()
    {
        return view('mesaservicio::livewire.reportes.index', [
            // Tope defensivo, mismo criterio que Livewire\Tecnicos\Show (ver
            // docs/mesaservicio-progreso.md, Fase 2) — el repo todavía no
            // tiene un componente de paginación reusable.
            'reports' => SdpReport::orderByDesc('periodo')->limit(100)->get(),
        ]);
    }
}
