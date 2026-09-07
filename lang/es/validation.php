<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'accepted' => 'Debes aceptar :attribute.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'email' => 'El campo :attribute debe ser un correo electrónico válido.',
    'max' => [
        'string' => 'El campo :attribute no debe superar :max caracteres.',
    ],
    'min' => [
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'unique' => 'El :attribute ya está en uso.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'login' => 'correo, RFC, nombre o teléfono',
        'email' => 'correo electrónico',
        'password' => 'contraseña',
        'name' => 'nombre',
        'rfc' => 'RFC',
        'phone' => 'teléfono',
    ],

];
