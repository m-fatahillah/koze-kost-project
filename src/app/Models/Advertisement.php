<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Advertisement extends Model
{
    protected $fillable = [
        'title',
        'advertiser_name',
        'image_url',
        'target_url',
        'placement',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public static function activeFor(string $placement): ?self
    {
        $today = now()->toDateString();
        return static::query()
            ->where('placement', $placement)
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderByDesc('created_at')
            ->first();
    }

    public function toBannerArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'advertiser_name' => $this->advertiser_name,
            'image_url' => $this->image_url ? '/storage/' . $this->image_url : null,
            'target_url' => $this->target_url,
        ];
    }
}
