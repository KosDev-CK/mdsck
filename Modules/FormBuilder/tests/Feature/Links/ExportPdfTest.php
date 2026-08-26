<?php

namespace Modules\FormBuilder\Tests\Feature\Links;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Modules\FormBuilder\Livewire\Forms\Manage;
use Modules\FormBuilder\Livewire\Links\Show;
use Modules\FormBuilder\Models\Form;
use Modules\FormBuilder\Models\FormAnswer;
use Modules\FormBuilder\Models\FormField;
use Modules\FormBuilder\Models\FormSubmission;
use Modules\FormBuilder\Models\TicketFormLink;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExportPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function screens(): void
    {
        Screen::create([
            'module' => 'FormBuilder', 'name' => 'Formularios', 'slug' => 'formbuilder',
            'route_name' => 'formbuilder.forms.index', 'permission_name' => 'screens.formbuilder.manage',
            'icon' => 'clipboard-document-list', 'order' => 1,
        ]);
        Screen::create([
            'module' => 'FormBuilder', 'name' => 'Mis Formularios', 'slug' => 'formbuilder-mis-formularios',
            'route_name' => 'formbuilder.links.index', 'permission_name' => 'screens.formbuilder.capture',
            'icon' => 'pencil-square', 'order' => 2,
        ]);
    }

    protected function userWith(string $permission): User
    {
        $role = Role::findOrCreate('Rol '.$permission, 'web');
        $role->givePermissionTo($permission);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    protected function answeredLink(?string $pdfTemplate = null): TicketFormLink
    {
        $creator = User::factory()->create(['is_active' => true]);
        $form = Form::create([
            'name' => 'Alta de Usuario', 'status' => 'published',
            'pdf_template' => $pdfTemplate, 'created_by' => $creator->id,
        ]);

        $field = FormField::create([
            'form_id' => $form->id, 'type' => 'short_text', 'label' => 'Nombre del Empleado',
            'field_key' => 'nombre_del_empleado', 'is_required' => true, 'order' => 0,
        ]);

        [, $hash] = TicketFormLink::generateToken();
        $link = TicketFormLink::create([
            'form_id' => $form->id, 'ticket_number' => 'INC1', 'recipient_email' => 'a@example.com',
            'token_hash' => $hash, 'expires_at' => now()->addDay(), 'used_at' => now(), 'created_by' => $creator->id,
        ]);

        $submission = FormSubmission::create([
            'form_id' => $form->id, 'ticket_form_link_id' => $link->id, 'submitted_at' => now(),
        ]);

        FormAnswer::create(['submission_id' => $submission->id, 'form_field_id' => $field->id, 'value' => 'Juan Pérez']);

        return $link->fresh();
    }

    public function test_manage_validates_and_persists_the_pdf_template(): void
    {
        $this->screens();
        $user = $this->userWith('screens.formbuilder.manage');
        $form = Form::create(['name' => 'Prueba', 'status' => 'draft', 'created_by' => $user->id]);

        Livewire::actingAs($user)
            ->test(Manage::class)
            ->call('selectForm', $form->id)
            ->set('pdfTemplate', 'no-existe')
            ->call('savePdfTemplate')
            ->assertHasErrors('pdfTemplate');

        Livewire::actingAs($user)
            ->test(Manage::class)
            ->call('selectForm', $form->id)
            ->set('pdfTemplate', 'alta_usuario')
            ->call('savePdfTemplate')
            ->assertHasNoErrors();

        $this->assertSame('alta_usuario', $form->fresh()->pdf_template);
    }

    public function test_manage_can_edit_the_name_and_description_of_an_existing_form(): void
    {
        $this->screens();
        $user = $this->userWith('screens.formbuilder.manage');
        $form = Form::create(['name' => 'Vieja', 'description' => 'Vieja desc', 'status' => 'draft', 'created_by' => $user->id]);

        Livewire::actingAs($user)
            ->test(Manage::class)
            ->call('selectForm', $form->id)
            ->call('editDetails')
            ->assertSet('editName', 'Vieja')
            ->set('editName', 'FMSOL002-NuevoNombre')
            ->set('editDescription', 'Nueva desc')
            ->call('saveDetails')
            ->assertSet('editingDetails', false)
            ->assertHasNoErrors();

        $form->refresh();
        $this->assertSame('FMSOL002-NuevoNombre', $form->name);
        $this->assertSame('Nueva desc', $form->description);
    }

    public function test_manage_can_customize_the_download_file_name(): void
    {
        $this->screens();
        $user = $this->userWith('screens.formbuilder.manage');
        $form = Form::create(['name' => 'FMSOL001-MultiFormatoSolicitud', 'status' => 'draft', 'created_by' => $user->id]);
        $other = Form::create(['name' => 'Otro', 'slug' => 'otro-formato', 'status' => 'draft', 'created_by' => $user->id]);

        Livewire::actingAs($user)
            ->test(Manage::class)
            ->call('selectForm', $form->id)
            ->call('editDetails')
            ->set('editSlug', 'otro-formato')
            ->call('saveDetails')
            ->assertHasErrors('editSlug');

        Livewire::actingAs($user)
            ->test(Manage::class)
            ->call('selectForm', $form->id)
            ->call('editDetails')
            ->set('editSlug', 'Alta Usuario!!')
            ->call('saveDetails')
            ->assertHasNoErrors();

        $this->assertSame('alta-usuario', $form->fresh()->slug);
    }

    public function test_show_exports_a_pdf_using_the_custom_template(): void
    {
        $this->screens();
        $link = $this->answeredLink('alta_usuario');
        $link->form->update(['slug' => 'alta-usuario']);
        $viewer = $this->userWith('screens.formbuilder.capture');
        $link->update(['created_by' => $viewer->id]);

        Livewire::actingAs($viewer)
            ->test(Show::class, ['ticketFormLink' => $link])
            ->call('exportPdf')
            ->assertFileDownloaded('alta-usuario-INC1.pdf');
    }

    public function test_show_exports_a_generic_pdf_when_no_template_is_assigned(): void
    {
        $this->screens();
        $link = $this->answeredLink(null);
        $viewer = $this->userWith('screens.formbuilder.capture');
        $link->update(['created_by' => $viewer->id]);

        Livewire::actingAs($viewer)
            ->test(Show::class, ['ticketFormLink' => $link])
            ->call('exportPdf')
            ->assertFileDownloaded();
    }

    public function test_internal_print_route_requires_ownership(): void
    {
        $this->screens();
        $link = $this->answeredLink('alta_usuario');
        $owner = $this->userWith('screens.formbuilder.capture');
        $link->update(['created_by' => $owner->id]);
        $other = $this->userWith('screens.formbuilder.capture');

        $this->actingAs($owner)->get(route('formbuilder.links.print', $link))->assertOk();
        $this->actingAs($other)->get(route('formbuilder.links.print', $link))->assertForbidden();
    }

    public function test_public_print_route_requires_a_valid_signature(): void
    {
        $link = $this->answeredLink('alta_usuario');

        $this->get(route('formbuilder.public.print', ['ticketFormLink' => $link->id]))->assertForbidden();

        $signed = URL::temporarySignedRoute('formbuilder.public.print', now()->addMinutes(10), ['ticketFormLink' => $link->id]);
        $this->get($signed)->assertOk();
    }
}
