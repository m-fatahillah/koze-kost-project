<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeaturedListingPayment extends Model
{
    protected $fillable = [
        'property_id',
        'owner_id',
        'months',
        'price_per_month',
        'total_amount',
        'bank_name',
        'account_number',
        'account_holder_name',
        'payment_proof',
        'status',
        'rejection_reason',
        'verified_by',
        'verified_at',
        'starts_at',
        'expires_at',
    ];

    protected $casts = [
        'price_per_month' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'months' => 'integer',
        'verified_at' => 'datetime',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
