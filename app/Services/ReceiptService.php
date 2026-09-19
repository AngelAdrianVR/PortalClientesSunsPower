<?php

namespace App\Services;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Genera la URL pública de los comprobantes (recibos de pago / abonos).
 *
 * Los archivos viven en el disco compartido `erp_media`, cuyo root es el
 * storage público del ERP. Por eso la URL se construye contra el dominio
 * REAL del ERP (config `portal.erp_storage_url`, p.ej. https://erp-spmx.com/storage),
 * no contra el dominio del portal: así los comprobantes se ven aunque el
 * portal corra en local o en un subdominio.
 */
class ReceiptService
{
    public static function url(?Media $media): ?string
    {
        if (! $media) {
            return null;
        }

        $base = rtrim((string) config('portal.erp_storage_url'), '/');

        // Sin base configurada se conserva la descarga autenticada del portal.
        // La URL se devuelve SIN host (`false`): así el comprobante se descarga
        // del mismo servidor en el que se está viendo el portal, sin depender de
        // que APP_URL coincida con el dominio/puerto real (localhost:8000,
        // vhost local o el dominio de producción).
        if ($base === '') {
            return route('media.download', $media, false);
        }

        return $base.'/'.$media->id.'/'.rawurlencode($media->file_name);
    }
}
