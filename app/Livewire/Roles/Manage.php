<?php

namespace App\Livewire\Roles;

use App\Models\Screen;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
class Manage extends Component
{
    public string $newRoleName = '';

    public ?int $selectedRoleId = null;

    public array $selectedPermissions = [];

    public function mount()
    {
        $this->selectedRoleId = Role::query()->orderBy('name')->value('id');
        $this->loadSelectedPermissions();
    }

    public function selectRole(int $roleId)
    {
        $this->selectedRoleId = $roleId;
        $this->loadSelectedPermissions();
    }

    protected function loadSelectedPermissions(): void
    {
        if (! $this->selectedRoleId) {
            $this->selectedPermissions = [];

            return;
        }

        $role = Role::find($this->selectedRoleId);

        $this->selectedPermissions = $role?->permissions->pluck('name')->all() ?? [];
    }

    public function createRole()
    {
        $this->validate([
            'newRoleName' => ['required', 'string', 'max:255', 'unique:roles,name'],
        ]);

        $role = Role::create(['name' => $this->newRoleName, 'guard_name' => 'web']);

        $this->newRoleName = '';
        $this->selectRole($role->id);
    }

    public function deleteRole(int $roleId)
    {
        $role = Role::find($roleId);

        if (! $role || $role->name === 'Administrador') {
            return;
        }

        $role->delete();

        if ($this->selectedRoleId === $roleId) {
            $this->selectedRoleId = Role::query()->orderBy('name')->value('id');
            $this->loadSelectedPermissions();
        }
    }

    public function savePermissions()
    {
        $role = Role::find($this->selectedRoleId);

        if (! $role) {
            return;
        }

        $role->syncPermissions($this->selectedPermissions);

        session()->flash('status', 'Permisos actualizados.');
    }

    public function render()
    {
        return view('livewire.roles.manage', [
            'roles' => Role::withCount('users')->orderBy('name')->get(),
            'screens' => Screen::where('is_active', true)->orderBy('order')->get(),
            'selectedRole' => $this->selectedRoleId ? Role::find($this->selectedRoleId) : null,
        ]);
    }
}
