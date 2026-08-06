<?php

namespace Tests\Feature\Profile;

use App\Livewire\Profile\Show;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_update_their_own_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        Livewire::actingAs($user)
            ->test(Show::class)
            ->set('name', 'New Name')
            ->call('updateName')
            ->assertHasNoErrors();

        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_a_user_can_update_their_corporate_data(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Show::class)
            ->set('company', 'Kosmos')
            ->set('cedis', 'CEDIS Norte')
            ->set('area', 'TI')
            ->set('employeeNumber', 'EMP-123')
            ->set('location', 'Monterrey')
            ->call('updateName')
            ->assertHasNoErrors();

        $fresh = $user->fresh();
        $this->assertSame('Kosmos', $fresh->company);
        $this->assertSame('CEDIS Norte', $fresh->cedis);
        $this->assertSame('TI', $fresh->area);
        $this->assertSame('EMP-123', $fresh->employee_number);
        $this->assertSame('Monterrey', $fresh->location);
    }

    public function test_a_user_can_set_their_home_screen_to_one_they_can_access(): void
    {
        $screen = Screen::create([
            'name' => 'Conexiones a BD',
            'slug' => 'connections',
            'route_name' => 'connections.index',
            'permission_name' => 'screens.connections.manage',
            'order' => 1,
        ]);

        $role = Role::findOrCreate('Conexiones', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create();
        $user->assignRole($role);

        Livewire::actingAs($user)
            ->test(Show::class)
            ->set('homeScreenId', $screen->id)
            ->call('updateHomeScreen')
            ->assertHasNoErrors();

        $this->assertSame($screen->id, $user->fresh()->home_screen_id);
    }

    public function test_a_user_cannot_set_a_home_screen_they_cannot_access(): void
    {
        $screen = Screen::create([
            'name' => 'Conexiones a BD',
            'slug' => 'connections',
            'route_name' => 'connections.index',
            'permission_name' => 'screens.connections.manage',
            'order' => 1,
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Show::class)
            ->set('homeScreenId', $screen->id)
            ->call('updateHomeScreen')
            ->assertHasErrors('homeScreenId');

        $this->assertNull($user->fresh()->home_screen_id);
    }

    public function test_a_user_can_reset_their_home_screen_to_the_default(): void
    {
        $screen = Screen::create([
            'name' => 'Conexiones a BD',
            'slug' => 'connections',
            'route_name' => 'connections.index',
            'permission_name' => 'screens.connections.manage',
            'order' => 1,
        ]);

        $user = User::factory()->create(['home_screen_id' => $screen->id]);

        Livewire::actingAs($user)
            ->test(Show::class)
            ->set('homeScreenId', null)
            ->call('updateHomeScreen')
            ->assertHasNoErrors();

        $this->assertNull($user->fresh()->home_screen_id);
    }

    public function test_a_user_can_enable_two_factor_with_a_valid_code(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(Show::class)
            ->call('startEnablingTwoFactor');

        $secret = $component->get('pendingSecret');
        $validCode = (new Google2FA)->getCurrentOtp($secret);

        $component->set('confirmationCode', $validCode)
            ->call('confirmTwoFactor')
            ->assertHasNoErrors();

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $this->assertNotEmpty($component->get('recoveryCodes'));
    }

    public function test_a_user_can_download_their_recovery_codes_as_a_text_file(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(Show::class)
            ->call('startEnablingTwoFactor');

        $secret = $component->get('pendingSecret');
        $validCode = (new Google2FA)->getCurrentOtp($secret);

        $component->set('confirmationCode', $validCode)
            ->call('confirmTwoFactor')
            ->assertHasNoErrors();

        $component->call('downloadRecoveryCodes')
            ->assertFileDownloaded('mds-codigos-recuperacion.txt');
    }

    public function test_enabling_two_factor_with_a_wrong_code_fails(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Show::class)
            ->call('startEnablingTwoFactor')
            ->set('confirmationCode', '000000')
            ->call('confirmTwoFactor')
            ->assertHasErrors('confirmationCode');

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_a_user_can_disable_two_factor_with_a_valid_code(): void
    {
        $secret = (new Google2FA)->generateSecretKey();

        $user = User::factory()->create([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $validCode = (new Google2FA)->getCurrentOtp($secret);

        Livewire::actingAs($user)
            ->test(Show::class)
            ->set('disableCode', $validCode)
            ->call('disableTwoFactor')
            ->assertHasNoErrors();

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }
}
