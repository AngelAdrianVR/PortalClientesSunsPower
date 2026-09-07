<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\PortalLoginService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Autenticación del PORTAL DE CLIENTES.
 *
 * La identidad es la tabla `clients` del ERP (guard 'portal'); NO se usan
 * registros de la tabla `users` (empleados del ERP) ni contraseñas.
 */
class PortalAuthController extends Controller
{
    /**
     * Muestra la pantalla de login.
     */
    public function showLogin(Request $request)
    {
        if (Auth::guard('portal')->check()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Login', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Inicia sesión identificando al cliente por nombre, RFC, correo o teléfono.
     */
    public function login(Request $request)
    {
        if (Auth::guard('portal')->check()) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'email' => ['required', 'string', 'max:255'],
        ]);

        /** @var Client|null $client */
        $client = app(PortalLoginService::class)->resolve($validated['email']);

        if (! $client) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        Auth::guard('portal')->login($client);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Cierra la sesión del portal y regresa al login.
     */
    public function logout(Request $request)
    {
        Auth::guard('portal')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
