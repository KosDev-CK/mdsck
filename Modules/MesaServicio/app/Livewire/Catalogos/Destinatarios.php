<?php

namespace Modules\MesaServicio\Livewire\Catalogos;

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\MesaServicio\Models\SdpReportRecipientEmail;

#[Layout('layouts.app')]
class Destinatarios extends Component
{
    public const ROL_SUPERVISOR = 'Supervisor Mesa de Servicio';

    public string $newEmail = '';

    public string $newNombre = '';

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

    public function removeEmail(int $id): void
    {
        SdpReportRecipientEmail::find($id)?->delete();
    }

    public function render()
    {
        return view('mesaservicio::livewire.catalogos.destinatarios', [
            'recipients' => SdpReportRecipientEmail::orderBy('email')->get(),
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
