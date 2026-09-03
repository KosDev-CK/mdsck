<?php

namespace Modules\GestionTI\Livewire\Configuracion;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionTI\Models\ConfiguracionDocumentos;

/**
 * "Configuración de Almacenamiento" (Fase 5, SharePoint) — decide, por tipo
 * de documento, si `DocumentoDigitalizado::storeUploaded()` sube a
 * SharePoint (Microsoft Graph) o se queda en el disco `public`
 * (comportamiento histórico, respaldo para los tipos no marcados). Pantalla
 * de configuración pura (sin lista/paginación), mismo espíritu que
 * `App\Livewire\Branding\Manage` sobre `SiteSetting::current()`.
 */
#[Layout('layouts.app')]
class AlmacenamientoDocumentos extends Component
{
    /** @var array<int, string> */
    public array $tiposSharepoint = [];

    public function mount(): void
    {
        $this->tiposSharepoint = ConfiguracionDocumentos::current()->tipos_sharepoint ?? [];
    }

    public function save(): void
    {
        $this->validate([
            'tiposSharepoint' => 'array',
            'tiposSharepoint.*' => ['string', Rule::in(ConfiguracionDocumentos::TIPOS_DOCUMENTO)],
        ]);

        ConfiguracionDocumentos::current()->update([
            'tipos_sharepoint' => array_values($this->tiposSharepoint),
        ]);

        session()->flash('status', 'Configuración de almacenamiento actualizada.');
    }

    /**
     * Etiquetas legibles por tipo de documento — pública porque se invoca
     * desde la vista Blade.
     */
    public function tipoLabel(string $tipoDocumento): string
    {
        return match ($tipoDocumento) {
            'sic' => 'Adjunto de Solicitud de SIC',
            'responsiva' => 'Responsiva de Asignación de Activo',
            'remision_proveedor' => 'Remisión de Recepción de Proveedor',
            'factura' => 'Factura',
            'orden_servicio' => 'Orden de Servicio de Mantenimiento',
            default => $tipoDocumento,
        };
    }

    public function render()
    {
        return view('gestionti::livewire.configuracion.almacenamiento-documentos', [
            'tiposDocumento' => ConfiguracionDocumentos::TIPOS_DOCUMENTO,
        ]);
    }
}
