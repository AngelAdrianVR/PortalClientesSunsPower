<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Contact;
use Illuminate\Support\Collection;

/**
 * Identificación de quienes entran al PORTAL DE CLIENTES.
 *
 * Los usuarios del portal provienen EXCLUSIVAMENTE de la tabla `clients`
 * del ERP (NO de `users`, que es de empleados). Se identifica al cliente
 * con cualquiera de estos datos (sin importar mayúsculas/minúsculas):
 *   - nombre completo (clients.name) o persona de contacto (contact_person)
 *   - RFC (clients.tax_id)
 *   - correo o teléfono de un contacto del cliente (contacts)
 *
 * Solo se permite el acceso cuando hay UNA ficha de cliente inequívoca.
 */
class PortalLoginService
{
    public function resolve(string $identifier): ?Client
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        $lower = mb_strtolower($identifier);
        $digits = preg_replace('/\D/', '', $identifier);

        $candidates = new Collection();

        // Teléfono de contacto (solo dígitos)
        if ($digits !== '') {
            $candidates = $candidates->merge(
                Contact::query()
                    ->where('contactable_type', Client::class)
                    ->whereNotNull('phone')
                    ->get()
                    ->filter(fn (Contact $contact) => $contact->phone && preg_replace('/\D/', '', $contact->phone) === $digits)
                    ->map(fn (Contact $contact) => $contact->contactable)
                    ->filter()
            );
        }

        // Correo de contacto
        $candidates = $candidates->merge(
            Contact::query()
                ->where('contactable_type', Client::class)
                ->whereNotNull('email')
                ->get()
                ->filter(fn (Contact $contact) => mb_strtolower(trim((string) $contact->email)) === $lower)
                ->map(fn (Contact $contact) => $contact->contactable)
                ->filter()
        );

        // RFC
        $candidates = $candidates->merge(
            Client::whereRaw('LOWER(COALESCE(tax_id, \'\')) = ?', [$lower])->get()
        );

        // Nombre de la ficha o persona de contacto
        $candidates = $candidates->merge(
            Client::whereRaw('LOWER(TRIM(name)) = ?', [$lower])
                ->orWhereRaw('LOWER(TRIM(COALESCE(contact_person, \'\'))) = ?', [$lower])
                ->get()
        );

        $unique = $candidates->unique('id')->values();

        return $unique->count() === 1 ? $unique->first() : null;
    }
}
