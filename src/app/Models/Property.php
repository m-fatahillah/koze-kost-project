<?php

namespace App\Models;

use App\Enums\GenderRestriction;
use App\Enums\PriceType;
use App\Enums\PropertyStatus;
use App\Models\PropertyType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'owner_id', 
        'name', 
        'description', 
        'property_type_id', 
        'gender_restriction', 
        'price', 
        'price_type', 
        'address', 
        'latitude', 
        'longitude', 
        'total_rooms', 
        'available_rooms', 
        'is_featured',
        'featured_until',
        'status'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'price' => 'decimal:2',
        'latitude' => 'decimal:10',
        'longitude' => 'decimal:10',
        'property_type_id' => 'integer',
        'gender_restriction' => GenderRestriction::class,
        'price_type' => PriceType::class,
        'status' => PropertyStatus::class,
        'is_featured' => 'boolean',
        'featured_until' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'property_tags');
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'property_facility');
    }

    public function savedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_saved_properties')->withTimestamps();
    }

    public function propertyType()
    {
        return $this->belongsTo(PropertyType::class, 'property_type_id');
    }

    public function featuredListingPayments(): HasMany
    {
        return $this->hasMany(FeaturedListingPayment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
