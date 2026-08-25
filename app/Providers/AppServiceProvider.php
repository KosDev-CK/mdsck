<?php

namespace App\Providers;

use App\Mail\Transport\MicrosoftGraphTransport;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
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
        // IP(s) del reverse proxy de confianza (TRUSTED_PROXIES en .env), para que
        // request()->ip() devuelva el cliente real y request()->isSecure() detecte
        // https vía X-Forwarded-Proto. No puede fijarse en bootstrap/app.php: esa
        // clausura corre antes de que "config" esté disponible en el contenedor.
        TrustProxies::at(config('security.trusted_proxies'));

        // Defensa en profundidad sobre la carga de las pantallas de login/invitación por IP.
        // La protección real contra fuerza bruta vive en GuardsAgainstFlooding, dentro de
        // cada acción Livewire — esta ruta HTTP solo cubre la carga inicial de la página.
        RateLimiter::for('login-pages', function ($request) {
            return Limit::perMinute(config('security.max_requests_per_minute'))->by($request->ip());
        });

        // Mismo criterio que login-pages, pero en su propio bucket: tráfico pesado
        // (o abusivo) hacia un enlace público de formulario por ticket no debe
        // compartir destino con intentos reales de login desde la misma IP.
        RateLimiter::for('public-form-pages', function ($request) {
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
                proxy: config('services.microsoft_graph.proxy'),
            );
        });
    }
}
