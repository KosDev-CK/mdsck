<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Administrador base
    |--------------------------------------------------------------------------
    |
    | CoreSeeder crea (o conserva) este usuario con el rol "Administrador" y
    | todos los permisos. El comando "mds:clean-test-data" también usa este
    | correo como el usuario a preservar por defecto. Al clonar la plantilla
    | para un proyecto nuevo, ajusta estos dos valores en el .env — no hace
    | falta tocar código.
    |
    */
    'admin_email' => env('MDS_ADMIN_EMAIL', 'victor.gonzalez@landit.com.mx'),
    'admin_name' => env('MDS_ADMIN_NAME', 'Victor Gonzalez'),
];
