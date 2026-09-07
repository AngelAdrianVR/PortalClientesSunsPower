<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Cliente del ERP. Es la identidad autenticable del PORTAL DE CLIENTES:
 * la tabla `clients` (no `users`, que es de empleados del ERP) es la que
 * contiene a quienes pueden ingresar a este portal.
 */
class Client extends Model implements AuthenticatableContract
{
    use AuthenticatableTrait;

    protected $table = 'clients';

    protected $fillable = [
        'branch_id',
        'name',
        'contact_person',
        'tax_id',
        'type',
        'lead_source',
        'road_type',
        'street',
        'exterior_number',
        'interior_number',
        'neighborhood',
        'municipality',
        'state',
        'zip_code',
        'country',
        'coordinates',
        'notes',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function contacts(): MorphMany
    {
        return $this->morphMany(Contact::class, 'contactable');
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function portalPayments(): HasMany
    {
        return $this->hasMany(PortalPayment::class);
    }

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            trim(($this->road_type ? $this->road_type.' ' : '').($this->street ?? '')),
            $this->exterior_number ? '#'.$this->exterior_number : null,
            $this->interior_number ? 'Int. '.$this->interior_number : null,
            $this->neighborhood,
            $this->municipality,
            $this->state,
        ]);

        return implode(', ', $parts) ?: 'Sin dirección registrada';
    }
}
