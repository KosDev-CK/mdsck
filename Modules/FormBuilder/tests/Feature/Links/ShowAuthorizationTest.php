<?php

namespace Modules\FormBuilder\Tests\Feature\Links;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FormBuilder\Models\Form;
use Modules\FormBuilder\Models\TicketFormLink;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShowAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function screenAndRole(): Role
    {
        $screen = Screen::create([
            'module' => 'FormBuilder',
            'name' => 'Mis Formularios',
            'slug' => 'formbuilder-mis-formularios',
            'route_name' => 'formbuilder.links.index',
            'permission_name' => 'screens.formbuilder.capture',
            'icon' => 'pencil-square',
            'order' => 1,
        ]);

        $role = Role::findOrCreate('Solicitante', 'web');
        $role->givePermissionTo($screen->permission_name);

        return $role;
    }

    protected function createLink(User $creator): TicketFormLink
    {
        $form = Form::create(['name' => 'Alta de equipo', 'status' => 'published', 'created_by' => $creator->id]);
        [, $hash] = TicketFormLink::generateToken();

        return TicketFormLink::create([
            'form_id' => $form->id,
            'ticket_number' => 'INC000123',
            'recipient_email' => 'destinatario@example.com',
            'token_hash' => $hash,
            'expires_at' => now()->addHours(24),
            'created_by' => $creator->id,
        ]);
    }

    public function test_the_creator_can_view_the_link(): void
    {
        $role = $this->screenAndRole();
        $creator = User::factory()->create(['is_active' => true]);
        $creator->assignRole($role);
        $link = $this->createLink($creator);

        $this->actingAs($creator)
            ->get(route('formbuilder.links.show', $link))
            ->assertOk();
    }

    public function test_an_administrador_can_view_any_link(): void
    {
        $role = $this->screenAndRole();
        $creator = User::factory()->create(['is_active' => true]);
        $creator->assignRole($role);
        $link = $this->createLink($creator);

        $adminRole = Role::findOrCreate('Administrador', 'web');
        $adminRole->givePermissionTo($role->permissions);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole($adminRole);

        $this->actingAs($admin)
            ->get(route('formbuilder.links.show', $link))
            ->assertOk();
    }

    public function test_another_user_with_the_screen_permission_cannot_view_someone_elses_link(): void
    {
        $role = $this->screenAndRole();
        $creator = User::factory()->create(['is_active' => true]);
        $creator->assignRole($role);
        $link = $this->createLink($creator);

        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole($role);

        $this->actingAs($other)
            ->get(route('formbuilder.links.show', $link))
            ->assertForbidden();
    }
}
