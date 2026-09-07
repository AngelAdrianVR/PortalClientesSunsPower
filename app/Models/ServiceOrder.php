<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Espejo de solo lectura de la tabla `service_orders` del ERP.
 * Un "servicio contratado" del portal = una orden de servicio aceptada.
 */
class ServiceOrder extends Model
{
    protected $table = 'service_orders';

    /** Estados visibles en el portal como "servicios contratados". */
    public const PORTAL_STATUSES = ['Aceptado', 'En Proceso', 'Completado', 'Facturado'];

    protected $fillable = [
        'branch_id',
        'client_id',
        'technician_id',
        'sales_rep_id',
        'status',
        'start_date',
        'completion_date',
        'service_number',
        'rate_type',
        'system_type',
        'voltage',
        'number_of_wires',
        'number_of_units',
        'unit_capacity',
        'total_capacity',
        'meter_number',
        'inventory_reconciled',
        'total_amount',
        'down_payment',
        'installation_street',
        'installation_exterior_number',
        'installation_interior_number',
        'installation_neighborhood',
        'installation_municipality',
        'installation_state',
        'installation_zip_code',
        'installation_country',
        'installation_lat',
        'installation_lng',
        'payment_method',
        'requires_pre_installation',
        'pre_installation_details',
        'pre_installation_assigned_to',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'completion_date' => 'datetime',
            'total_amount' => 'decimal:2',
            'down_payment' => 'decimal:2',
            'unit_capacity' => 'decimal:2',
            'total_capacity' => 'decimal:2',
            'inventory_reconciled' => 'boolean',
            'requires_pre_installation' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentInstallments(): HasMany
    {
        return $this->hasMany(PaymentInstallment::class)->orderBy('installment_number');
    }

    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }

    public function getInstallationAddressAttribute(): string
    {
        $parts = array_filter([
            trim(($this->installation_street ?? '').($this->installation_exterior_number ? ' #'.$this->installation_exterior_number : '')),
            $this->installation_interior_number ? 'Int. '.$this->installation_interior_number : null,
            $this->installation_neighborhood,
            $this->installation_municipality,
            $this->installation_state,
        ]);

        return implode(', ', $parts) ?: 'Sin dirección registrada';
    }
}
