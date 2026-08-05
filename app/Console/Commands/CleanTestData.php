<?php

namespace App\Console\Commands;

use App\Models\BrandingPreset;
use App\Models\SecurityEvent;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class CleanTestData extends Command
{
    protected $signature = 'mds:clean-test-data
        {--keep-email= : Correo del usuario administrador a conservar (por defecto, config("mds.admin_email"))}
        {--force : Omite la confirmación interactiva}';

    protected $description = 'Borra usuarios, roles, invitaciones, bitácora, notificaciones, conexiones a BD y datos de prueba, conservando solo el administrador base con los colores de branding por defecto';

    public function handle(): int
    {
        $keepEmail = $this->option('keep-email') ?: config('mds.admin_email');

        $keepUser = User::where('email', $keepEmail)->first();

        if (! $keepUser) {
            $this->error("No existe ningún usuario con el correo \"{$keepEmail}\". Corre \"php artisan db:seed --class=Database\\Seeders\\CoreSeeder\" primero, o pasa --keep-email=otro@correo.com.");

            return self::FAILURE;
        }

        $otherUsersCount = User::where('id', '!=', $keepUser->id)->count();
        $otherRolesCount = Role::where('name', '!=', 'Administrador')->count();
        $customPresetsCount = BrandingPreset::where('is_system', false)->count();

        $this->line('Esto va a:');
        $this->line("  - Conservar únicamente al usuario \"{$keepUser->email}\" ({$keepUser->name}), con su rol y 2FA intactos.");
        $this->line("  - Borrar los otros {$otherUsersCount} usuario(s) y los {$otherRolesCount} perfil(es) distintos de \"Administrador\".");
        $this->line('  - Borrar todas las invitaciones, bitácora de seguridad, notificaciones, códigos de acceso, sesiones activas y conexiones a BD registradas.');
        $this->line("  - Borrar los {$customPresetsCount} preset(s) de branding personalizados, conservando los 3 del sistema (Predeterminado, LandIT, Corporativo Kosmos).");
        $this->line('  - Restablecer los colores de branding al preset "Predeterminado".');
        $this->line('  - Reiniciar los contadores de bloqueo/sesión del usuario conservado.');
        $this->newLine();
        $this->warn('Esta acción no se puede deshacer.');

        if (! $this->option('force') && ! $this->confirm('¿Continuar?')) {
            $this->info('Cancelado, no se modificó nada.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($keepUser) {
            DB::table('notifications')->delete();
            DB::table('login_codes')->delete();
            SecurityEvent::query()->delete();
            DB::table('invitations')->delete();
            DB::table('sessions')->delete();
            DB::table('password_reset_tokens')->delete();
            DB::table('database_connections')->delete();

            BrandingPreset::where('is_system', false)->delete();
            SiteSetting::current()->update(SiteSetting::DEFAULTS);

            User::where('id', '!=', $keepUser->id)->delete();

            DB::table('model_has_roles')
                ->where('model_type', User::class)
                ->where('model_id', '!=', $keepUser->id)
                ->delete();

            DB::table('model_has_permissions')
                ->where('model_type', User::class)
                ->where('model_id', '!=', $keepUser->id)
                ->delete();

            Role::where('name', '!=', 'Administrador')->get()->each(fn (Role $role) => $role->delete());

            $keepUser->forceFill([
                'failed_login_attempts' => 0,
                'lockout_cycles' => 0,
                'locked_until' => null,
                'current_session_id' => null,
                'last_login_at' => null,
                'last_login_ip' => null,
            ])->save();

            if (DB::getSchemaBuilder()->hasTable('cache')) {
                DB::table('cache')->delete();
            }

            if (DB::getSchemaBuilder()->hasTable('jobs')) {
                DB::table('jobs')->delete();
            }

            if (DB::getSchemaBuilder()->hasTable('failed_jobs')) {
                DB::table('failed_jobs')->delete();
            }
        });

        $this->info("Listo. Solo queda \"{$keepUser->email}\" con acceso, rol Administrador y branding por defecto — invita al resto de usuarios desde \"Configuración de acceso\".");

        return self::SUCCESS;
    }
}
