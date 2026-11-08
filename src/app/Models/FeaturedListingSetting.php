<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeaturedListingSetting extends Model
{
    protected $fillable = [
        'price_per_month',
        'bank_name',
        'account_number',
        'account_holder_name',
    ];

    protected $casts = [
        'price_per_month' => 'decimal:2',
    ];

    public static function current(): self
    {
        return static::query()->orderBy('id')->first() ?? new self([
            'price_per_month' => 0,
            'bank_name' => '',
            'account_number' => '',
            'account_holder_name' => '',
        ]);
    }
}
