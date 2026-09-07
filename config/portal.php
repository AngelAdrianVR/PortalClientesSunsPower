<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Login por contraseña (temporalmente deshabilitado)
    |--------------------------------------------------------------------------
    |
    | Mientras sea `false`, el portal permite entrar únicamente con el
    | identificador (correo, RFC, nombre o teléfono) y NO exige contraseña.
    |
    | La lógica de contraseña se conserva intacta (App\Services\PortalLoginService
    | y el request de login aceptan el campo `password`). Para volver a exigirla:
    |   1) Pon esta bandera en `true`.
    |   2) En resources/js/Pages/Auth/Login.vue activa `PASSWORD_VISIBLE`.
    */
    'login_password_required' => false,

];
