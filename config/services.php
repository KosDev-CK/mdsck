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

    /*
    |--------------------------------------------------------------------------
    | ServiceDesk Plus Cloud (Modules/MesaServicio)
    |--------------------------------------------------------------------------
    |
    | API REST v3 de ManageEngine ServiceDesk Plus Cloud, autenticada vía
    | OAuth2 de Zoho Accounts. El "Self Client" registrado en la Zoho API
    | Console produce un refresh_token permanente (canjeado una sola vez a
    | partir de un grant token de ~10 min) — es ese refresh_token el que se
    | guarda aquí, no el grant token. Con él se piden access tokens nuevos
    | (~1h de vida) en cada request. Ver docs/servicedesk-plus-oauth.md.
    | "api_domain"/"accounts_domain" nunca se hardcodean: SDP tiene ~10
    | dominios regionales distintos (US/EU/IN/AU/JP/CA/UK) y cada instancia
    | usa el suyo.
    |
    */

    'servicedesk_plus' => [
        'client_id' => env('SDP_CLIENT_ID'),
        'client_secret' => env('SDP_CLIENT_SECRET'),
        'refresh_token' => env('SDP_REFRESH_TOKEN'),
        // Segmento de portal en la URL de la instancia SDP (ej. "tuempresa").
        'portal' => env('SDP_PORTAL'),
        'api_domain' => env('SDP_API_DOMAIN'),
        'accounts_domain' => env('SDP_ACCOUNTS_DOMAIN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Oracle EBS — Solicitudes Internas de Compra (Modules/GestionTI)
    |--------------------------------------------------------------------------
    |
    | Un solo endpoint (Oracle Integration Cloud), Basic Auth, con el
    | "método" seleccionado por query string (?method=requisition_header_line
    | | requisition_header_approved). Ver
    | Modules/GestionTI/app/Support/Ebs/EbsRequisitionsClient.php y
    | docs/gestionti-progreso.md.
    |
    */

    'ebs' => [
        'base_url' => env('EBS_BASE_URL'),
        'organization_code' => env('EBS_ORGANIZATION_CODE'),
        'username' => env('EBS_USERNAME'),
        'password' => env('EBS_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SharePoint vía Microsoft Graph — documentos digitalizados (Modules/GestionTI)
    |--------------------------------------------------------------------------
    |
    | Migra el almacenamiento de `DocumentoDigitalizado` de local a
    | SharePoint, configurable por tipo de documento (ver
    | `Modules\GestionTI\Models\ConfiguracionDocumentos`). Reutiliza el mismo
    | App Registration de Azure AD que ya usa el correo (`AZURE_MAIL_*`) —
    | mismo tenant/client id/secret, solo cambia el permiso de aplicación
    | consentido (`Sites.Selected` sobre el sitio específico, en vez de
    | `Mail.Send`). "site_hostname"/"site_path" identifican el sitio de
    | SharePoint (no hay `site_id` fijo, se resuelve en tiempo real vía
    | Graph — más portable entre entornos). "carpetas" mapea cada
    | `tipo_documento` a su carpeta dentro de la biblioteca default del
    | sitio — ver `Modules\GestionTI\Support\SharePoint\SharePointClient::carpetaParaTipoDocumento()`
    | para qué pasa si se activa un tipo sin carpeta configurada.
    |
    */

    'sharepoint' => [
        'tenant_id' => env('AZURE_MAIL_TENANT_ID'),
        'client_id' => env('AZURE_MAIL_CLIENT_ID'),
        'client_secret' => env('AZURE_MAIL_CLIENT_SECRET'),
        'site_hostname' => env('SHAREPOINT_SITE_HOSTNAME'),
        'site_path' => env('SHAREPOINT_SITE_PATH'),
        'proxy' => env('AZURE_MAIL_HTTP_PROXY'),
        'carpetas' => [
            'sic' => env('SHAREPOINT_FOLDER_SIC'),
            'responsiva' => env('SHAREPOINT_FOLDER_RESPONSIVA'),
            'remision_proveedor' => env('SHAREPOINT_FOLDER_REMISION_PROVEEDOR'),
            'factura' => env('SHAREPOINT_FOLDER_FACTURA'),
            'orden_servicio' => env('SHAREPOINT_FOLDER_ORDEN_SERVICIO'),
        ],
    ],

];
