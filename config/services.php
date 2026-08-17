<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Microsoft Graph (envío de correo con OAuth2, sin SMTP)
    |--------------------------------------------------------------------------
    |
    | Reemplaza SMTP + usuario/contraseña, que Microsoft está retirando.
    | Cada sitio basado en esta plantilla necesita su propio App Registration
    | en Entra ID (tenant/client id/secret propios) — ver
    | docs/correo-oauth2-azure.md. "sender" es el buzón desde el que se
    | envía (debe tener el permiso de aplicación Mail.Send concedido, e
    | idealmente restringido a este buzón vía Application Access Policy).
    |
    */

    'microsoft_graph' => [
        'tenant_id' => env('AZURE_MAIL_TENANT_ID'),
        'client_id' => env('AZURE_MAIL_CLIENT_ID'),
        'client_secret' => env('AZURE_MAIL_CLIENT_SECRET'),
        'sender' => env('AZURE_MAIL_SENDER', env('MAIL_FROM_ADDRESS')),
        // Forward proxy opcional (ej. http://10.0.0.5:3128) para cuando este
        // servidor no tiene salida directa a internet y solo puede alcanzar
        // login.microsoftonline.com/graph.microsoft.com a través de un proxy
        // saliente. Ver docs/correo-oauth2-azure.md.
        'proxy' => env('AZURE_MAIL_HTTP_PROXY'),
    ],

];
