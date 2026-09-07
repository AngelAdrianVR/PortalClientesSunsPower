<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PortalPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaController extends Controller
{
    /**
     * Descarga segura de comprobantes: solo el cliente dueño del pago/abono
     * puede acceder. El archivo vive en el disco compartido `erp_media`,
     * por lo que sirve tanto comprobantes del ERP como del portal.
     */
    public function show(Request $request, Media $media)
    {
        $clientId = (int) ($request->user('portal')?->id ?? 0);
        $model = $media->model;

        $allowed = false;

        if ($model instanceof Payment) {
            $allowed = (int) $model->client_id === $clientId;
        } elseif ($model instanceof PortalPayment) {
            $allowed = (int) $model->client_id === $clientId;
        }

        abort_unless($allowed, 404);

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk(PortalPayment::RECEIPT_DISK);

        $path = $media->getPath();

        if (! $disk->exists($path)) {
            abort(404, 'Comprobante no disponible.');
        }

        return $disk->response($path, $media->file_name);
    }
}
