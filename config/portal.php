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

    /*
    |--------------------------------------------------------------------------
    | URL pública de los comprobantes (storage del ERP)
    |--------------------------------------------------------------------------
    |
    | Base (incluye `/storage`) con la que el ERP sirve los comprobantes
    | compartidos, p. ej. `https://erp-spmx.com/storage`. Se usa para enlazar
    | los comprobantes directo al dominio real del ERP, de modo que puedan
    | verse aunque el portal corra en local o en un subdominio.
    |
    | Si queda vacío, se conserva la descarga autenticada vía el portal
    | (ruta `media.download`).
    */
    'erp_storage_url' => rtrim((string) env('ERP_STORAGE_URL', ''), '/'),

];
