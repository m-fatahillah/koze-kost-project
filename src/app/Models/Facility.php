<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Facility extends Model
{
    protected $fillable = [
        'name',
        'icon',
    ];

    public function properties()
    {
        return $this->belongsToMany(Property::class, 'property_facility');
    }
}
