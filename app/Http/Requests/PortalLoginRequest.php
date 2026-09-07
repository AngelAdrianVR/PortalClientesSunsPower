<?php

namespace App\Http\Requests;

use Laravel\Fortify\Http\Requests\LoginRequest;

/**
 * Request de login del portal.
 *
 * El campo `email` se reutiliza como IDENTIFICADOR (puede ser correo, RFC,
 * nombre o teléfono). Se mantiene el nombre `email` porque Fortify lo usa
 * internamente (p. ej. confirmación de contraseña con [username => email]).
 *
 * `password` es OPCIONAL por ahora (login sin contraseña en
 * config('portal.login_password_required') = false).
 */
class PortalLoginRequest extends LoginRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string'],
            'password' => ['nullable', 'string'],
            'remember' => ['sometimes'],
        ];
    }
}
