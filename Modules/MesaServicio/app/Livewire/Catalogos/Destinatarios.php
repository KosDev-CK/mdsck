<?php

namespace Modules\MesaServicio\Livewire\Catalogos;

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\FormBuilder\Models\Form;
use Modules\MesaServicio\Models\SdpReportRecipientEmail;
use Modules\MesaServicio\Models\SdpSurveySetting;

#[Layout('layouts.app')]
class Destinatarios extends Component
{
    public const ROL_SUPERVISOR = 'Supervisor Mesa de Servicio';

    public string $newEmail = '';

    public string $newNombre = '';

    /**
     * Id (como string, para el <select>) del Form de Modules\FormBuilder
     * configurado como encuesta de satisfacción — cadena vacía = "Ninguno"
     * (SdpSurveySetting.form_id null, disparo automático desactivado).
     */
    public string $surveyFormId = '';

    public function mount(): void
    {
        $this->surveyFormId = (string) (SdpSurveySetting::current()->form_id ?? '');
    }

    protected function rules(): array
    {
        return [
            'newEmail' => ['required', 'email', 'max:255', 'unique:sdp_report_recipient_emails,email'],
            'newNombre' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function addEmail(): void
    {
        $this->validate();

        SdpReportRecipientEmail::create([
            'email' => $this->newEmail,
            'nombre' => $this->newNombre !== '' ? $this->newNombre : null,
        ]);

        $this->reset(['newEmail', 'newNombre']);
        session()->flash('status', 'Correo agregado a la lista de destinatarios.');
    }

    /**
     * Esta es la única acción "eliminar" real de esta pantalla (correos
     * sueltos, catálogo simple sin FK entrantes) — a diferencia de otros
     * catálogos del módulo no hay toggleActivo aquí porque un correo
     * suelto inactivo no tiene sentido, solo existe/no existe. Se envuelve
     * en try/catch por consistencia con el resto de catálogos con "Eliminar"
     * (misma red de seguridad ante una FK restrict futura), aunque hoy
     * sdp_report_recipient_emails no tiene ninguna referencia entrante.
     */
    public function removeEmail(int $id): void
    {
        $recipient = SdpReportRecipientEmail::find($id);

        if (! $recipient) {
            return;
        }

        try {
            $recipient->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            session()->flash('error', 'No se puede eliminar: este registro está en uso en otro lugar del sistema.');

            return;
        }

        session()->flash('status', 'Correo quitado de la lista de destinatarios.');
    }

    /**
     * Guarda de inmediato al cambiar el <select> (sin botón "Guardar" aparte,
     * mismo criterio de UI mínima ya usado por el toggle de nivel 1 en
     * Catalogos\Tecnicos). form_id se valida contra Form::wherePublished()
     * antes de guardarlo — un id que ya no exista o dejó de estar publicado
     * simplemente no aparece en las opciones del <select>, pero se revalida
     * aquí por si el modelo se manipulara directo (ej. dos pestañas abiertas).
     */
    public function updatedSurveyFormId(string $value): void
    {
        $formId = $value !== '' ? (int) $value : null;

        if ($formId !== null && ! Form::wherePublished()->whereKey($formId)->exists()) {
            $this->surveyFormId = (string) (SdpSurveySetting::current()->form_id ?? '');
            session()->flash('error', 'Selecciona un formulario publicado válido.');

            return;
        }

        SdpSurveySetting::current()->update(['form_id' => $formId]);

        session()->flash('status', $formId
            ? 'Encuesta de satisfacción activada con el formulario seleccionado.'
            : 'Encuesta de satisfacción desactivada — no se enviará ningún correo automático.');
    }

    public function render()
    {
        return view('mesaservicio::livewire.catalogos.destinatarios', [
            'recipients' => SdpReportRecipientEmail::orderBy('email')->get(),
            'surveyForms' => Form::wherePublished()->orderBy('name')->get(),
            // El otro destinatario es un rol completo de Spatie, resuelto en
            // tiempo real — no se guarda nada de esto en BD (ver
            // docs/mesaservicio-progreso.md, Fase 1). Se usa whereHas en vez
            // del scope role() de Spatie porque este último lanza
            // RoleDoesNotExist si el rol no existe en la BD (por ejemplo, en
            // un test que no corrió el seeder del módulo) — aquí preferimos
            // una lista vacía a un error 500.
            'supervisors' => User::whereHas('roles', fn ($query) => $query->where('name', self::ROL_SUPERVISOR))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }
}
