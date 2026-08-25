<?php

namespace Modules\FormBuilder\Tests\Feature\Links;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\FormBuilder\Livewire\Links\Send;
use Modules\FormBuilder\Models\Form;
use Modules\FormBuilder\Models\TicketFormLink;
use Modules\FormBuilder\Notifications\TicketFormLinkNotification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GenerateLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function actingSender(): User
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

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_generating_a_link_creates_a_record_and_sends_the_notification(): void
    {
        Notification::fake();

        $user = $this->actingSender();
        $form = Form::create(['name' => 'Alta de equipo', 'status' => 'published', 'created_by' => $user->id]);

        Livewire::actingAs($user)
            ->test(Send::class)
            ->set('formId', (string) $form->id)
            ->set('ticketNumber', 'INC000123')
            ->set('recipientEmail', 'destinatario@example.com')
            ->call('generateLink')
            ->assertHasNoErrors();

        $link = TicketFormLink::first();
        $this->assertNotNull($link);
        $this->assertSame('INC000123', $link->ticket_number);
        $this->assertSame('destinatario@example.com', $link->recipient_email);
        $this->assertSame($user->id, $link->created_by);
        $this->assertSame('pending', $link->status());

        Notification::assertSentOnDemand(TicketFormLinkNotification::class);
    }

    public function test_cannot_generate_a_link_for_an_unpublished_form(): void
    {
        Notification::fake();

        $user = $this->actingSender();
        $form = Form::create(['name' => 'Borrador', 'status' => 'draft', 'created_by' => $user->id]);

        Livewire::actingAs($user)
            ->test(Send::class)
            ->set('formId', (string) $form->id)
            ->set('ticketNumber', 'INC000124')
            ->set('recipientEmail', 'destinatario@example.com')
            ->call('generateLink')
            ->assertHasErrors('formId');

        $this->assertDatabaseCount('ticket_form_links', 0);
        Notification::assertNothingSent();
    }
}
