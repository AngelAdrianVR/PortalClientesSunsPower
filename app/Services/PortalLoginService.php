<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Contact;
use Illuminate\Support\Facades\Log;

/**
 * Identificación de quienes entran al PORTAL DE CLIENTES.
 *
 * Los usuarios del portal provienen EXCLUSIVAMENTE de la tabla `clients`
 * del ERP (NO de `users`, que es de empleados). Se identifica al cliente
 * con cualquiera de estos datos:
 *   - nombre completo (clients.name) o persona de contacto (contact_person)
 *   - RFC (clients.tax_id)
 *   - correo o teléfono de un contacto del cliente (contacts)
 *
 * La comparación de texto es tolerante: no distingue mayúsculas de minúsculas,
 * ignora acentos (RODRIGUEZ = RODRÍGUEZ) y unifica espacios de más o espacios
 * "duros". Se hace así —y en PHP, no en SQL— para que el resultado no dependa
 * de la collation de MySQL del servidor: la misma persona debe poder entrar
 * igual en local y en producción.
 *
 * Solo se permite el acceso cuando hay UNA ficha de cliente inequívoca.
 */
class PortalLoginService
{
    /** Cuántos nombres parecidos se guardan en el log cuando no hay coincidencia. */
    private const SIMILAR_LIMIT = 5;

    /**
     * Acentos que se ignoran al comparar: así "RODRIGUEZ" encuentra a
     * "RODRÍGUEZ". La ñ NO se convierte en n a propósito (PEÑA ≠ PENA).
     */
    private const ACCENTS = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ç' => 'c',
    ];

    public function resolve(string $identifier): ?Client
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        // Comparación en PHP con texto normalizado (minúsculas, sin acentos y
        // con espacios simples): NO depende de la collation de MySQL ni de cómo
        // quedó capturado el nombre (acentos, dobles espacios, espacios duros…).
        // La tabla de clientes es pequeña y esto solo ocurre al iniciar sesión.
        $normalized = $this->normalize($identifier);
        $digits = preg_replace('/\D/', '', $identifier) ?: '';

        $ids = [];
        $similar = [];

        // Contactos: teléfono (solo dígitos) y correo (sin distinguir mayúsculas).
        foreach (Contact::query()->where('contactable_type', Client::class)->get() as $contact) {
            $matchesPhone = $digits !== '' && preg_replace('/\D/', '', (string) $contact->phone) === $digits;
            $matchesEmail = $this->normalize($contact->email) === $normalized;

            if ($matchesPhone || $matchesEmail) {
                $ids[] = (int) $contact->contactable_id;
            }
        }

        // Ficha del cliente: RFC, razón social y persona de contacto.
        $firstWord = explode(' ', $normalized)[0] ?? '';

        Client::query()
            ->select(['id', 'name', 'contact_person', 'tax_id'])
            ->chunkById(500, function ($clients) use ($normalized, $firstWord, &$ids, &$similar) {
                foreach ($clients as $client) {
                    $name = $this->normalize($client->name);
                    $contactPerson = $this->normalize($client->contact_person);

                    if ($name === $normalized
                        || $contactPerson === $normalized
                        || $this->normalize($client->tax_id) === $normalized) {
                        $ids[] = (int) $client->id;

                        continue;
                    }

                    // Nombres parecidos: solo para poder diagnosticar desde el log.
                    if (count($similar) < self::SIMILAR_LIMIT
                        && $firstWord !== ''
                        && (str_contains($name, $firstWord) || str_contains($contactPerson, $firstWord))) {
                        $similar[] = trim($client->name.' (id '.$client->id.')');
                    }
                }
            });

        $ids = array_values(array_unique($ids));

        if (count($ids) === 1) {
            return Client::find($ids[0]);
        }

        // Sin coincidencia (o con varias candidatas): se registra para poder
        // saber desde el log si el cliente no existe o si está escrito distinto.
        Log::notice('Portal: identificación sin coincidencia única.', [
            'identificador' => $identifier,
            'coincidencias' => count($ids),
            'nombres_parecidos' => $similar,
        ]);

        return null;
    }

    /**
     * Texto comparable: minúsculas, sin acentos y con espacios simples.
     * Los espacios "duros" (no separables) y los repetidos se unifican, porque
     * es habitual que vengan pegados desde Excel o desde el ERP.
     */
    private function normalize(?string $value): string
    {
        $value = str_replace(["\u{A0}", "\u{2007}", "\u{202F}"], ' ', (string) $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = mb_strtolower(trim($value));

        return strtr($value, self::ACCENTS);
    }
}
