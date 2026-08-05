<?php

namespace App\Livewire\UserRoles;

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
class Manage extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $selectedUserId = null;

    public array $selectedRoles = [];

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function selectUser(int $userId)
    {
        $this->selectedUserId = $userId;
        $this->selectedRoles = User::find($userId)?->roles->pluck('name')->all() ?? [];
    }

    public function cancelEdit()
    {
        $this->selectedUserId = null;
        $this->selectedRoles = [];
    }

    public function saveRoles()
    {
        $user = User::find($this->selectedUserId);

        if (! $user) {
            return;
        }

        // The seeded Administrador account always keeps its role so the
        // system can never be locked out of its own admin screens.
        if ($user->hasRole('Administrador') && ! in_array('Administrador', $this->selectedRoles, true)) {
            $this->selectedRoles[] = 'Administrador';
        }

        $user->syncRoles($this->selectedRoles);

        session()->flash('status', 'Perfiles actualizados para '.$user->name.'.');
    }

    public function render()
    {
        return view('livewire.user-roles.manage', [
            'users' => User::query()
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->paginate(10),
            'roles' => Role::orderBy('name')->get(),
            'selectedUser' => $this->selectedUserId ? User::find($this->selectedUserId) : null,
        ]);
    }
}
