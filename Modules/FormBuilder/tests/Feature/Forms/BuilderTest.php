<?php

namespace Modules\FormBuilder\Tests\Feature\Forms;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\FormBuilder\Livewire\Forms\Builder;
use Modules\FormBuilder\Models\Form;
use Modules\FormBuilder\Models\FormField;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function actingManager(): User
    {
        $screen = Screen::create([
            'module' => 'FormBuilder',
            'name' => 'Formularios',
            'slug' => 'formbuilder',
            'route_name' => 'formbuilder.forms.index',
            'permission_name' => 'screens.formbuilder.manage',
            'icon' => 'clipboard-document-list',
            'order' => 1,
        ]);

        $role = Role::findOrCreate('Gestor de formularios', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_can_create_edit_and_delete_a_field(): void
    {
        $user = $this->actingManager();
        $form = Form::create(['name' => 'Encuesta', 'status' => 'draft', 'created_by' => $user->id]);

        Livewire::actingAs($user)
            ->test(Builder::class, ['form' => $form])
            ->set('label', 'Nombre completo')
            ->set('type', 'short_text')
            ->set('isRequired', true)
            ->call('saveField')
            ->assertHasNoErrors();

        $field = FormField::where('form_id', $form->id)->first();
        $this->assertNotNull($field);
        $this->assertSame('Nombre completo', $field->label);
        $this->assertTrue($field->is_required);

        Livewire::actingAs($user)
            ->test(Builder::class, ['form' => $form])
            ->call('editField', $field->id)
            ->set('label', 'Nombre y apellido')
            ->call('saveField')
            ->assertHasNoErrors();

        $this->assertSame('Nombre y apellido', $field->fresh()->label);

        Livewire::actingAs($user)
            ->test(Builder::class, ['form' => $form])
            ->call('deleteField', $field->id);

        $this->assertDatabaseMissing('form_fields', ['id' => $field->id]);
    }

    public function test_a_repeater_field_persists_its_sub_fields(): void
    {
        $user = $this->actingManager();
        $form = Form::create(['name' => 'Inventario', 'status' => 'draft', 'created_by' => $user->id]);

        Livewire::actingAs($user)
            ->test(Builder::class, ['form' => $form])
            ->set('label', 'Artículos')
            ->set('type', 'repeater')
            ->set('subFields', [
                ['id' => null, 'field_key' => null, 'type' => 'short_text', 'label' => 'Artículo', 'options' => [], 'is_required' => true],
                ['id' => null, 'field_key' => null, 'type' => 'number', 'label' => 'Cantidad', 'options' => [], 'is_required' => true],
            ])
            ->call('saveField')
            ->assertHasNoErrors();

        $parent = FormField::where('form_id', $form->id)->where('type', 'repeater')->first();
        $this->assertNotNull($parent);
        $this->assertCount(2, $parent->children);
        $this->assertSame(['Artículo', 'Cantidad'], $parent->children->pluck('label')->all());
    }

    public function test_choice_fields_require_at_least_one_option(): void
    {
        $user = $this->actingManager();
        $form = Form::create(['name' => 'Encuesta', 'status' => 'draft', 'created_by' => $user->id]);

        Livewire::actingAs($user)
            ->test(Builder::class, ['form' => $form])
            ->set('label', 'Color favorito')
            ->set('type', 'single_choice')
            ->set('options', [])
            ->call('saveField')
            ->assertHasErrors('options');
    }
}
