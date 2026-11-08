<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use App\Models\Faq;
use App\Models\Property;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        $faqs = Faq::query()
            ->get()
            ->map(function ($faq) {
                return [
                    'label' => $faq->pertanyaan,
                    'content' => $faq->jawaban,
                ];
            })
            ->values()
            ->all();

        $featuredProperties = Property::query()
            ->where('status', 'active')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with([
                'images' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('created_at'),
                'facilities:id,name,icon',
            ])
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->limit(12)
            ->get()
            ->map(function ($property) {
                $primaryImage = $property->images->firstWhere('is_primary', true) ?? $property->images->first();

                return [
                    'id' => $property->id,
                    'name' => $property->name,
                    'address' => $property->address,
                    'price' => (float) $property->price,
                    'price_type' => $property->price_type?->value ?? $property->price_type,
                    'gender_restriction' => $property->gender_restriction?->value ?? $property->gender_restriction,
                    'type' => $property->type?->value ?? $property->type,
                    'latitude' => (float) $property->latitude,
                    'longitude' => (float) $property->longitude,
                    'is_featured' => (bool) $property->is_featured,
                    'thumbnail_url' => $primaryImage ? '/storage/' . $primaryImage->image_url : null,
                    'description' => $property->description,
                    'facilities' => $property->facilities->map(fn ($facility) => [
                        'id' => $facility->id,
                        'name' => $facility->name,
                        'icon' => $facility->icon,
                    ])->values(),
                ];
            })
            ->values()
            ->all();

        $sidebarAd = Advertisement::activeFor('sidebar');

        return Inertia::render('Welcome', [
            'appName' => config('app.name', 'Koze'),
            'faqs' => $faqs,
            'featuredProperties' => $featuredProperties,
            'sidebarAd' => $sidebarAd?->toBannerArray(),
        ]);
    }
}
