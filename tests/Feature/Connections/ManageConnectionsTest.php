<?php

namespace Tests\Feature\Connections;

use App\Livewire\Connections\Manage;
use App\Models\DatabaseConnection;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManageConnectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAdmin(): User
    {
        $screen = Screen::create([
            'name' => 'Conexiones a BD',
            'slug' => 'connections',
            'route_name' => 'connections.index',
            'permission_name' => 'screens.connections.manage',
            'order' => 1,
        ]);

        $adminRole = Role::findOrCreate('Administrador', 'web');
        $adminRole->givePermissionTo($screen->permission_name);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole($adminRole);

        return $admin;
    }

    public function test_an_admin_can_create_a_mysql_connection_with_an_encrypted_password(): void
    {
        $admin = $this->actingAdmin();

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->call('create')
            ->set('name', 'Reportes Externos')
            ->set('key', 'reportes_externos')
            ->set('driver', 'mysql')
            ->set('mode', 'single')
            ->set('host', '127.0.0.1')
            ->set('port', 3306)
            ->set('database', 'reportes')
            ->set('username', 'reportes_user')
            ->set('password', 'super-secret')
            ->call('save')
            ->assertHasNoErrors();

        $connection = DatabaseConnection::where('key', 'reportes_externos')->first();

        $this->assertNotNull($connection);
        $this->assertSame('super-secret', $connection->password);
        $this->assertDatabaseMissing('database_connections', ['password' => 'super-secret']);
    }

    public function test_editing_a_connection_without_a_new_password_keeps_the_existing_one(): void
    {
        $admin = $this->actingAdmin();

        $connection = DatabaseConnection::create([
            'name' => 'Reportes',
            'key' => 'reportes',
            'driver' => 'mysql',
            'mode' => 'single',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'reportes',
            'username' => 'user',
            'password' => 'original-secret',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->call('edit', $connection->id)
            ->set('name', 'Reportes (renombrado)')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('original-secret', $connection->fresh()->password);
        $this->assertSame('Reportes (renombrado)', $connection->fresh()->name);
    }

    public function test_api_connections_do_not_require_host_or_database(): void
    {
        $admin = $this->actingAdmin();

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->call('create')
            ->set('name', 'API de Nómina')
            ->set('key', 'api_nomina')
            ->set('driver', 'api')
            ->set('baseUrl', 'https://api.example.com/nomina')
            ->call('save')
            ->assertHasNoErrors();

        $connection = DatabaseConnection::where('key', 'api_nomina')->first();

        $this->assertNotNull($connection);
        $this->assertNull($connection->host);
        $this->assertSame('https://api.example.com/nomina', $connection->base_url);
    }

    public function test_testing_an_api_connection_reports_success_on_a_2xx_response(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $admin = $this->actingAdmin();

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->call('create')
            ->set('driver', 'api')
            ->set('baseUrl', 'https://api.example.com/health')
            ->set('name', 'Salud API')
            ->set('key', 'salud_api')
            ->call('testConnection')
            ->assertSet('testResult', fn ($value) => str_starts_with($value, 'ok:'));
    }
}
