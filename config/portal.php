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

    /*
    |--------------------------------------------------------------------------
    | Tutoriales: panel de gestión oculto
    |--------------------------------------------------------------------------
    |
    | Los tutoriales que se muestran en la sección "Tutoriales" del menú
    | lateral se administran desde un panel sin ningún enlace en la interfaz:
    | solo se entra escribiendo la URL.
    |
    |   /gestion-tutoriales        → entra directo (llave vacía)
    |
    | Si defines `TUTORIALS_ADMIN_KEY` con una cadena larga, el panel queda
    | protegido: hay que entrar UNA vez con
    |
    |   /gestion-tutoriales?llave=LA_LLAVE
    |
    | y desde ahí queda autorizado en la sesión (la URL se mantiene simple).
    | Con llave configurada, la URL sin llave responde 404.
    */
    'tutorials_admin_key' => (string) env('TUTORIALS_ADMIN_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Tutoriales: tamaño máximo por archivo (MB)
    |--------------------------------------------------------------------------
    |
    | El límite real es el menor entre este valor y el de php.ini
    | (upload_max_filesize / post_max_size). El panel de gestión muestra los
    | tres valores para saber por qué un archivo pesado no sube.
    |
    | Valores sugeridos en php.ini (o .user.ini en el hosting):
    |   upload_max_filesize = 300M
    |   post_max_size       = 310M   (siempre un poco mayor)
    |   max_execution_time  = 600
    |   max_input_time      = 600
    |
    | Recomendación: recomprimir el video antes de subirlo (720p, ~1 Mbps) o
    | subirlo como enlace de YouTube/Vimeo, en lugar del master original.
    */
    'tutorials_max_upload_mb' => (int) env('TUTORIALS_MAX_UPLOAD_MB', 300),

];
