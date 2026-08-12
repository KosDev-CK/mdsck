<?php

namespace App\Providers;

use App\Mail\Transport\MicrosoftGraphTransport;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Defensa en profundidad sobre la carga de las pantallas de login/invitación por IP.
        // La protección real contra fuerza bruta vive en GuardsAgainstFlooding, dentro de
        // cada acción Livewire — esta ruta HTTP solo cubre la carga inicial de la página.
        RateLimiter::for('login-pages', function ($request) {
            return Limit::perMinute(config('security.max_requests_per_minute'))->by($request->ip());
        });

        // Envío de correo vía Microsoft Graph (OAuth2 app-only), en vez de SMTP con
        // usuario/contraseña — ver docs/correo-oauth2-azure.md.
        Mail::extend('graph', function () {
            return new MicrosoftGraphTransport(
                tenantId: config('services.microsoft_graph.tenant_id'),
                clientId: config('services.microsoft_graph.client_id'),
                clientSecret: config('services.microsoft_graph.client_secret'),
                sender: config('services.microsoft_graph.sender'),
            );
        });
    }
}
