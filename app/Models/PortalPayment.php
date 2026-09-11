<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Abono registrado por el cliente desde el portal.
 *
 * Nace "En revisión" y NO afecta el saldo hasta que el personal del ERP
 * lo valide (→ Completado) o lo rechace (→ Rechazado). El comprobante se
 * guarda en el disco compartido `erp_media` (storage público del ERP).
 */
class PortalPayment extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const STATUS_IN_REVIEW = 'En revisión';
    public const STATUS_COMPLETED = 'Completado';
    public const STATUS_REJECTED = 'Rechazado';

    public const METHODS = ['Transferencia', 'Efectivo', 'Cheque', 'Tarjeta', 'Depósito', 'Otro'];

    /** Disco compartido con el storage público del ERP. */
    public const RECEIPT_DISK = 'erp_media';

    protected $table = 'portal_payments';

    protected $fillable = [
        'branch_id',
        'client_id',
        'service_order_id',
        // Cuota de la proyección (payment_installments) que el cliente eligió pagar.
        'installment_number',
        'amount',
        'payment_date',
        'method',
        'reference',
        'notes',
        'status',
        'validated_by',
        'validated_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'validated_at' => 'datetime',
            'installment_number' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('receipts')->singleFile();
    }
}
