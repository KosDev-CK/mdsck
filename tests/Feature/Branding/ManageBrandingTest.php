<?php

namespace Tests\Feature\Branding;

use App\Livewire\Branding\Manage;
use App\Models\BrandingPreset;
use App\Models\Screen;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManageBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAdmin(): User
    {
        $screen = Screen::create([
            'name' => 'Branding',
            'slug' => 'branding',
            'route_name' => 'branding.index',
            'permission_name' => 'screens.branding.manage',
            'order' => 1,
        ]);

        $adminRole = Role::findOrCreate('Administrador', 'web');
        $adminRole->givePermissionTo($screen->permission_name);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole($adminRole);

        return $admin;
    }

    public function test_an_admin_can_update_the_brand_colors(): void
    {
        $admin = $this->actingAdmin();

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->set('primaryColor', '#123456')
            ->set('successColor', '#22AA33')
            ->set('dangerColor', '#AA2222')
            ->set('warningColor', '#CCAA00')
            ->set('infoColor', '#2266AA')
            ->call('saveColors')
            ->assertHasNoErrors();

        $settings = SiteSetting::current();
        $this->assertSame('#123456', $settings->primary_color);
        $this->assertSame('#22AA33', $settings->success_color);
    }

    public function test_an_admin_can_update_the_topbar_and_sidebar_colors(): void
    {
        $admin = $this->actingAdmin();

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->set('topbarColor', '#ABCDEF')
            ->set('sidebarHeaderColor', '#111111')
            ->set('sidebarBodyColor', '#222222')
            ->call('saveColors')
            ->assertHasNoErrors();

        $settings = SiteSetting::current();
        $this->assertSame('#ABCDEF', $settings->topbar_color);
        $this->assertSame('#111111', $settings->sidebar_header_color);
        $this->assertSame('#222222', $settings->sidebar_body_color);
    }

    public function test_a_preset_carries_the_full_site_configuration_not_just_the_five_base_colors(): void
    {
        $admin = $this->actingAdmin();
        $preset = BrandingPreset::create([
            'name' => 'Prueba completa',
            'primary_color' => '#F36522',
            'success_color' => '#4C9A63',
            'danger_color' => '#D6432A',
            'warning_color' => '#F2B705',
            'info_color' => '#2C7FB8',
            'topbar_color' => '#FFFFFF',
            'sidebar_header_color' => '#F36522',
            'sidebar_body_color' => '#000000',
            'is_system' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->call('applyPreset', $preset->id)
            ->assertSet('sidebarHeaderColor', '#F36522')
            ->assertSet('sidebarBodyColor', '#000000');

        $settings = SiteSetting::current();
        $this->assertSame('#F36522', $settings->sidebar_header_color);
        $this->assertSame('#000000', $settings->sidebar_body_color);
    }

    public function test_the_default_preset_restores_the_original_colors(): void
    {
        $admin = $this->actingAdmin();

        $default = BrandingPreset::create(array_merge(
            ['name' => 'Predeterminado', 'is_system' => true],
            SiteSetting::DEFAULTS
        ));

        SiteSetting::current()->update(['primary_color' => '#000000']);

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->call('applyPreset', $default->id)
            ->assertSet('primaryColor', SiteSetting::DEFAULTS['primary_color']);

        $this->assertSame(SiteSetting::DEFAULTS['primary_color'], SiteSetting::current()->primary_color);
    }

    public function test_an_invalid_hex_color_is_rejected(): void
    {
        $admin = $this->actingAdmin();

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->set('primaryColor', 'not-a-color')
            ->call('saveColors')
            ->assertHasErrors('primaryColor');
    }

    public function test_an_admin_can_upload_a_logo_and_favicon(): void
    {
        Storage::fake('public');
        $admin = $this->actingAdmin();

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->set('logo', UploadedFile::fake()->image('logo.png'))
            ->set('favicon', UploadedFile::fake()->image('favicon.png'))
            ->call('saveIdentity')
            ->assertHasNoErrors();

        $settings = SiteSetting::current();
        Storage::disk('public')->assertExists($settings->logo_path);
        Storage::disk('public')->assertExists($settings->favicon_path);
    }

    public function test_applying_a_preset_replaces_the_active_colors(): void
    {
        $admin = $this->actingAdmin();
        $preset = BrandingPreset::create([
            'name' => 'Prueba',
            'primary_color' => '#F36522',
            'success_color' => '#4C9A63',
            'danger_color' => '#D6432A',
            'warning_color' => '#F2B705',
            'info_color' => '#2C7FB8',
            'is_system' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->call('applyPreset', $preset->id)
            ->assertSet('primaryColor', '#F36522');

        $this->assertSame('#F36522', SiteSetting::current()->primary_color);
    }

    public function test_the_current_colors_can_be_saved_as_a_new_preset(): void
    {
        $admin = $this->actingAdmin();

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->set('primaryColor', '#ABCDEF')
            ->set('newPresetName', 'Mi preset')
            ->call('saveAsPreset')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('branding_presets', [
            'name' => 'Mi preset',
            'primary_color' => '#ABCDEF',
            'is_system' => false,
        ]);
    }

    public function test_a_system_preset_cannot_be_deleted(): void
    {
        $admin = $this->actingAdmin();
        $preset = BrandingPreset::create([
            'name' => 'LandIT',
            'primary_color' => '#F36522',
            'success_color' => '#4C9A63',
            'danger_color' => '#D6432A',
            'warning_color' => '#F2B705',
            'info_color' => '#2C7FB8',
            'is_system' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->call('deletePreset', $preset->id);

        $this->assertDatabaseHas('branding_presets', ['id' => $preset->id]);
    }

    public function test_guests_and_unauthorized_users_cannot_reach_the_screen(): void
    {
        $this->actingAdmin();

        $this->get(route('branding.index'))->assertRedirect(route('login'));

        $plainUser = User::factory()->create(['is_active' => true]);
        $this->actingAs($plainUser)->get(route('branding.index'))->assertForbidden();
    }
}
