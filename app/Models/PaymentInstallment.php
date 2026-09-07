<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Espejo de la tabla `payment_installments` del ERP.
 * Replica las reglas de interés moratorio del ERP para mostrarlas en el portal
 * sin mutar la base de datos.
 */
class PaymentInstallment extends Model
{
    /** Tasa de interés moratorio: 10% mensual compuesto. */
    public const MONTHLY_INTEREST_RATE = 0.10;

    /** Días de gracia antes de aplicar interés. */
    public const GRACE_PERIOD_DAYS = 5;

    protected $table = 'payment_installments';

    protected $fillable = [
        'service_order_id',
        'installment_number',
        'label',
        'projected_date',
        'amount',
        'apply_interest',
        'status',
        'paid_amount',
        'paid_date',
        'payment_id',
    ];

    protected function casts(): array
    {
        return [
            'projected_date' => 'date',
            'paid_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'apply_interest' => 'boolean',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isPaid(): bool
    {
        return $this->payment_id !== null || in_array($this->status, ['paid', 'on_time'], true);
    }

    /** Interés moratorio acumulado (0 si está pagada o sin retraso). */
    public function calculateInterest(): float
    {
        if ($this->isPaid() || $this->apply_interest === false) {
            return 0.0;
        }

        $daysSince = (int) $this->projected_date->startOfDay()->diffInDays(now()->startOfDay(), false);
        $lateDays = $daysSince - self::GRACE_PERIOD_DAYS;

        if ($lateDays <= 0) {
            return 0.0;
        }

        $months = (int) ceil($lateDays / 30);

        return round(((float) $this->amount * pow(1 + self::MONTHLY_INTEREST_RATE, $months)) - (float) $this->amount, 2);
    }

    public function getDaysLateAttribute(): int
    {
        if ($this->isPaid()) {
            return 0;
        }

        $daysSince = (int) $this->projected_date->startOfDay()->diffInDays(now()->startOfDay(), false);

        return max(0, $daysSince - self::GRACE_PERIOD_DAYS);
    }

    public function getMonthsOfInterestAttribute(): int
    {
        return $this->days_late > 0 ? (int) ceil($this->days_late / 30) : 0;
    }

    public function getTotalWithInterestAttribute(): float
    {
        return round((float) $this->amount + $this->calculateInterest(), 2);
    }

    /** Estatus calculado al vuelo según las reglas del ERP (no muta la BD). */
    public function getCurrentStatusAttribute(): string
    {
        if ($this->isPaid()) {
            return $this->status === 'on_time' ? 'on_time' : 'paid';
        }

        $daysSince = (int) $this->projected_date->startOfDay()->diffInDays(now()->startOfDay(), false);

        if ($daysSince < 0) {
            return 'upcoming';
        }
        if ($daysSince <= 5) {
            return 'pending';
        }
        if ($daysSince <= 10) {
            return 'late';
        }

        return 'defaulted';
    }
}
