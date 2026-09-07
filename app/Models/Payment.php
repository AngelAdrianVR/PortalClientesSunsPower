<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Espejo de la tabla `payments` del ERP (abonos y pagos validados por la empresa).
 */
class Payment extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'payments';

    protected $fillable = [
        'branch_id',
        'client_id',
        'service_order_id',
        'installment_number',
        'amount',
        'interest_amount',
        'payment_date',
        'method',
        'reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('receipts');
    }

    /** Importe que abona a capital (excluye intereses moratorios). */
    public function getPrincipalAttribute(): float
    {
        return round((float) $this->amount - (float) $this->interest_amount, 2);
    }
}
