<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use App\Models\Faq;
use App\Models\FeaturedListingPayment;
use App\Models\Property;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function discover(): Response
    {
        $fallbackGradients = [
            'linear-gradient(140deg, #b1b8c8 0%, #ddd1bb 45%, #f4efe7 100%)',
            'linear-gradient(140deg, #325364 0%, #4e7f93 45%, #b3d0dc 100%)',
            'linear-gradient(140deg, #3f3e45 0%, #6f665f 45%, #d3c7b7 100%)',
            'linear-gradient(140deg, #6b4b5f 0%, #97708d 45%, #e8dbe3 100%)',
            'linear-gradient(140deg, #46634a 0%, #6f926f 45%, #d8e8d5 100%)',
        ];

        $properties = Property::query()
            ->with([
                'propertyType:id,name',
                'facilities:id,name,icon',
                'images' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('created_at'),
            ])
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Property $property): array => $this->mapDiscoverProperty($property, $fallbackGradients))
            ->values()
            ->all();

        $sidebarAd = Advertisement::activeFor('sidebar');

        return Inertia::render('Discover', [
            'properties' => $properties,
            'sidebarAd' => $sidebarAd?->toBannerArray(),
        ]);
    }

    public function saved(): Response
    {
        $fallbackGradients = [
            'linear-gradient(140deg, #b1b8c8 0%, #ddd1bb 45%, #f4efe7 100%)',
            'linear-gradient(140deg, #325364 0%, #4e7f93 45%, #b3d0dc 100%)',
            'linear-gradient(140deg, #3f3e45 0%, #6f665f 45%, #d3c7b7 100%)',
            'linear-gradient(140deg, #6b4b5f 0%, #97708d 45%, #e8dbe3 100%)',
            'linear-gradient(140deg, #46634a 0%, #6f926f 45%, #d8e8d5 100%)',
        ];

        $user = request()->user();

        $savedProperties = $user
            ? $user->savedProperties()
                ->with([
                    'propertyType:id,name',
                    'facilities:id,name,icon',
                    'images' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('created_at'),
                ])
                ->orderByPivot('created_at', 'desc')
                ->get()
            : collect();

        $mappedSaved = $savedProperties
            ->map(function (Property $property) use ($fallbackGradients): array {
                $savedAt = $property->pivot?->created_at?->toIso8601String();

                return [
                    ...$this->mapDiscoverProperty($property, $fallbackGradients),
                    'savedAt' => $savedAt,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Saved', [
            'properties' => $mappedSaved,
        ]);
    }

    public function propertyShow(int $id): Response
    {
        $fallbackGradients = [
            'linear-gradient(140deg, #b1b8c8 0%, #ddd1bb 45%, #f4efe7 100%)',
            'linear-gradient(140deg, #325364 0%, #4e7f93 45%, #b3d0dc 100%)',
            'linear-gradient(140deg, #3f3e45 0%, #6f665f 45%, #d3c7b7 100%)',
            'linear-gradient(140deg, #6b4b5f 0%, #97708d 45%, #e8dbe3 100%)',
            'linear-gradient(140deg, #46634a 0%, #6f926f 45%, #d8e8d5 100%)',
        ];

        $property = Property::query()
            ->with([
                'owner:id,name,phone',
                'propertyType:id,name',
                'facilities:id,name,icon',
                'images' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('created_at'),
                'reviews' => fn ($query) => $query->orderByDesc('created_at')->with('seeker:id,name,avatar'),
            ])
            ->where('status', 'active')
            ->find($id);

        if (!$property) {
            return Inertia::render('PropertyDetail', [
                'property' => null,
                'similarProperties' => [],
            ]);
        }

        $similarProperties = Property::query()
            ->with([
                'propertyType:id,name',
                'facilities:id,name,icon',
                'images' => fn ($query) => $query->orderByDesc('is_primary')->orderByDesc('created_at'),
            ])
            ->where('status', 'active')
            ->where('id', '!=', $property->id)
            ->where('gender_restriction', $property->gender_restriction)
            ->orderByDesc('created_at')
            ->limit(3)
            ->get()
            ->map(fn (Property $property): array => $this->mapDiscoverProperty($property, $fallbackGradients))
            ->values()
            ->all();

        return Inertia::render('PropertyDetail', [
            'property' => $this->mapPropertyDetail($property, $fallbackGradients),
            'similarProperties' => $similarProperties,
        ]);
    }

    public function messagesIndex(): Response
    {
        return Inertia::render('Messages');
    }

    public function dashboard(): Response
    {
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->hasRole('Admin');

        return Inertia::render('Dashboard', [
            'role' => $isAdmin ? 'Admin' : 'Owner',
            'stats' => $isAdmin ? $this->adminStats() : $this->ownerStats($user),
            'charts' => $isAdmin ? $this->adminCharts() : $this->ownerCharts($user),
            'recent' => $isAdmin ? $this->adminRecent() : $this->ownerRecent($user),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function adminStats(): array
    {
        $totalUsers = User::count();
        $totalOwners = User::role('Owner')->count();
        $totalSeekers = User::role('Seeker')->count();
        $totalProperties = Property::count();
        $featuredActive = Property::where('is_featured', true)->count();
        $pendingPayments = FeaturedListingPayment::where('status', 'pending')->count();
        $revenue = (float) FeaturedListingPayment::where('status', 'verified')->sum('total_amount');
        $activeAds = Advertisement::where('status', 'active')
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->count();

        $startOfMonth = Carbon::now()->startOfMonth();
        $newUsersThisMonth = User::where('created_at', '>=', $startOfMonth)->count();
        $newPropertiesThisMonth = Property::where('created_at', '>=', $startOfMonth)->count();

        return [
            ['key' => 'users',      'title' => 'Total Pengguna',        'value' => $totalUsers,        'icon' => 'i-lucide-users',          'tone' => 'sky',     'delta' => $newUsersThisMonth,        'deltaLabel' => 'baru bulan ini'],
            ['key' => 'owners',     'title' => 'Pemilik Properti',      'value' => $totalOwners,       'icon' => 'i-lucide-briefcase',      'tone' => 'indigo',  'delta' => null,                       'deltaLabel' => 'pemilik aktif'],
            ['key' => 'seekers',    'title' => 'Pencari Kos',           'value' => $totalSeekers,      'icon' => 'i-lucide-user-search',    'tone' => 'emerald', 'delta' => null,                       'deltaLabel' => 'pengguna pencari'],
            ['key' => 'properties', 'title' => 'Total Properti',        'value' => $totalProperties,   'icon' => 'i-lucide-home',           'tone' => 'amber',   'delta' => $newPropertiesThisMonth,    'deltaLabel' => 'baru bulan ini'],
            ['key' => 'featured',   'title' => 'Iklan Eksklusif Aktif', 'value' => $featuredActive,    'icon' => 'i-lucide-star',           'tone' => 'fuchsia', 'delta' => $pendingPayments,           'deltaLabel' => 'menunggu verifikasi'],
            ['key' => 'revenue',    'title' => 'Pendapatan Iklan',      'value' => $revenue,           'icon' => 'i-lucide-banknote',       'tone' => 'rose',    'delta' => null,                       'deltaLabel' => 'akumulasi terverifikasi', 'format' => 'currency'],
            ['key' => 'ads',        'title' => 'Iklan Aktif',           'value' => $activeAds,         'icon' => 'i-lucide-megaphone',      'tone' => 'cyan',    'delta' => null,                       'deltaLabel' => 'tayang hari ini'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ownerStats(User $user): array
    {
        $ownerId = $user->id;

        $myProperties = Property::where('owner_id', $ownerId)->count();
        $activeProperties = Property::where('owner_id', $ownerId)->where('status', 'active')->count();
        $totalRooms = (int) Property::where('owner_id', $ownerId)->sum('total_rooms');
        $availableRooms = (int) Property::where('owner_id', $ownerId)->sum('available_rooms');
        $occupiedRooms = max(0, $totalRooms - $availableRooms);
        $featuredActive = Property::where('owner_id', $ownerId)->where('is_featured', true)->count();
        $pendingPayments = FeaturedListingPayment::where('owner_id', $ownerId)->where('status', 'pending')->count();
        $spend = (float) FeaturedListingPayment::where('owner_id', $ownerId)->where('status', 'verified')->sum('total_amount');

        return [
            ['key' => 'my_props',   'title' => 'Properti Saya',          'value' => $myProperties,     'icon' => 'i-lucide-home',         'tone' => 'sky',     'delta' => $activeProperties,    'deltaLabel' => 'status aktif'],
            ['key' => 'rooms',      'title' => 'Total Kamar',            'value' => $totalRooms,       'icon' => 'i-lucide-bed-double',   'tone' => 'indigo',  'delta' => $availableRooms,      'deltaLabel' => 'kamar tersedia'],
            ['key' => 'occupied',   'title' => 'Kamar Terisi',           'value' => $occupiedRooms,    'icon' => 'i-lucide-users',        'tone' => 'emerald', 'delta' => null,                  'deltaLabel' => 'penghuni aktif'],
            ['key' => 'featured',   'title' => 'Listing Premium Aktif',  'value' => $featuredActive,   'icon' => 'i-lucide-star',         'tone' => 'amber',   'delta' => $pendingPayments,     'deltaLabel' => 'menunggu verifikasi'],
            ['key' => 'spend',      'title' => 'Total Belanja Listing',  'value' => $spend,            'icon' => 'i-lucide-banknote',     'tone' => 'rose',    'delta' => null,                  'deltaLabel' => 'akumulasi pengeluaran', 'format' => 'currency'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminCharts(): array
    {
        $months = $this->monthBuckets(6);

        $userSeries = $this->seriesByMonth(User::query(), $months);
        $propertySeries = $this->seriesByMonth(Property::query(), $months);

        return [
            'primary' => [
                'title' => 'Pertumbuhan Pengguna',
                'subtitle' => 'Registrasi pengguna baru per bulan',
                'labels' => array_column($months, 'label'),
                'series' => $userSeries,
                'color' => '#2563eb',
            ],
            'secondary' => [
                'title' => 'Properti Baru',
                'subtitle' => 'Properti terdaftar per bulan',
                'labels' => array_column($months, 'label'),
                'series' => $propertySeries,
                'color' => '#7c3aed',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ownerCharts(User $user): array
    {
        $months = $this->monthBuckets(6);
        $ownerId = $user->id;

        $myPropertySeries = $this->seriesByMonth(Property::query()->where('owner_id', $ownerId), $months);

        $verifiedPaymentSpend = $this->seriesByMonth(
            FeaturedListingPayment::query()
                ->where('owner_id', $ownerId)
                ->where('status', 'verified'),
            $months,
            'total_amount'
        );

        return [
            'primary' => [
                'title' => 'Pertumbuhan Properti',
                'subtitle' => 'Properti terdaftar per bulan',
                'labels' => array_column($months, 'label'),
                'series' => $myPropertySeries,
                'color' => '#0ea5e9',
            ],
            'secondary' => [
                'title' => 'Belanja Listing Premium',
                'subtitle' => 'Total pembayaran terverifikasi per bulan',
                'labels' => array_column($months, 'label'),
                'series' => $verifiedPaymentSpend,
                'color' => '#f59e0b',
                'format' => 'currency',
            ],
        ];
    }

    /**
     * Buat array bulan-bulan terakhir (paling lama → terbaru).
     * @return array<int, array{key:string,label:string,start:string,end:string}>
     */
    private function monthBuckets(int $count): array
    {
        $buckets = [];
        $monthNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        for ($i = $count - 1; $i >= 0; $i--) {
            $start = Carbon::now()->startOfMonth()->subMonths($i);
            $buckets[] = [
                'key' => $start->format('Y-m'),
                'label' => $monthNames[$start->month - 1],
                'start' => $start->toDateTimeString(),
                'end' => $start->copy()->endOfMonth()->toDateTimeString(),
            ];
        }
        return $buckets;
    }

    /**
     * Ambil agregat per-bulan dari query yang sudah di-scope.
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array<int, array{key:string,start:string,end:string}> $months
     * @return array<int, float>
     */
    private function seriesByMonth($query, array $months, ?string $sumColumn = null): array
    {
        $base = clone $query;
        $rows = $base
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, " . ($sumColumn ? "SUM($sumColumn) as agg" : 'COUNT(*) as agg'))
            ->whereBetween('created_at', [$months[0]['start'], $months[count($months) - 1]['end']])
            ->groupBy('ym')
            ->pluck('agg', 'ym');

        $series = [];
        foreach ($months as $bucket) {
            $series[] = (float) ($rows[$bucket['key']] ?? 0);
        }
        return $series;
    }

    /**
     * @return array<string, mixed>
     */
    private function adminRecent(): array
    {
        $pendingPayments = FeaturedListingPayment::with(['property:id,name', 'owner:id,name'])
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'property' => $p->property?->name,
                'owner' => $p->owner?->name,
                'months' => $p->months,
                'total' => (float) $p->total_amount,
                'created_at' => $p->created_at?->toIso8601String(),
            ])
            ->all();

        $latestProperties = Property::with('owner:id,name')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'owner' => $p->owner?->name,
                'status' => $p->status?->value ?? $p->status,
                'is_featured' => (bool) $p->is_featured,
                'created_at' => $p->created_at?->toIso8601String(),
            ])
            ->all();

        return [
            'pendingPayments' => $pendingPayments,
            'latestProperties' => $latestProperties,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ownerRecent(User $user): array
    {
        $ownerId = $user->id;

        $myProperties = Property::where('owner_id', $ownerId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'status' => $p->status?->value ?? $p->status,
                'is_featured' => (bool) $p->is_featured,
                'featured_until' => $p->featured_until?->toIso8601String(),
                'available_rooms' => (int) $p->available_rooms,
                'total_rooms' => (int) $p->total_rooms,
            ])
            ->all();

        $myPayments = FeaturedListingPayment::with('property:id,name')
            ->where('owner_id', $ownerId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'property' => $p->property?->name,
                'months' => $p->months,
                'total' => (float) $p->total_amount,
                'status' => $p->status,
                'created_at' => $p->created_at?->toIso8601String(),
            ])
            ->all();

        return [
            'myProperties' => $myProperties,
            'myPayments' => $myPayments,
        ];
    }

    /**
     * @param array<int, string> $fallbackGradients
     * @return array<string, mixed>
     */
    private function mapDiscoverProperty(Property $property, array $fallbackGradients): array
    {
        $addressParts = array_values(array_filter(array_map('trim', explode(',', (string) $property->address))));
        $location = $addressParts[0] ?? 'Lokasi belum diisi';
        $area = $addressParts[1] ?? $location;

        $genderValue = $property->gender_restriction->value ?? (string) $property->gender_restriction;
        $audience = match ($genderValue) {
            'putra' => 'Putra',
            'putri' => 'Putri',
            default => 'Campur',
        };

        $primaryImage = $property->images->firstWhere('is_primary', true) ?? $property->images->first();
        $cover = $fallbackGradients[$property->id % count($fallbackGradients)];
        $thumbnailUrl = null;

        if ($primaryImage && filled($primaryImage->image_url)) {
            $imageUrl = $this->resolveImageUrl($primaryImage->image_url);
            $cover = $this->buildImageStyle($imageUrl);
            $thumbnailUrl = $imageUrl;
        }

        $priceType = $property->price_type->value ?? (string) $property->price_type;
        $availability = $property->available_rooms > 0
            ? sprintf('%d kamar tersedia', $property->available_rooms)
            : 'Penuh';

        return [
            'id' => $property->id,
            'title' => $property->name,
            'area' => $area,
            'location' => $location,
            'audience' => $audience,
            'genderRestriction' => $genderValue,
            'isFeatured' => (bool) $property->is_featured,
            'propertyType' => $property->propertyType?->name,
            'description' => (string) $property->description,
            'priceValue' => (float) $property->price,
            'priceType' => $priceType,
            'rating' => 0,
            'createdAt' => $property->created_at?->toDateString() ?? now()->toDateString(),
            'facilities' => $property->facilities->pluck('name')->values()->all(),
            'facilitiesDetailed' => $property->facilities->map(fn ($facility) => [
                'id' => $facility->id,
                'name' => $facility->name,
                'icon' => $facility->icon ?? null,
            ])->values()->all(),
            'cover' => $cover,
            'thumbnailUrl' => $thumbnailUrl,
            'availability' => $availability,
            'latitude' => $property->latitude !== null ? (float) $property->latitude : null,
            'longitude' => $property->longitude !== null ? (float) $property->longitude : null,
        ];
    }

    /**
     * @param array<int, string> $fallbackGradients
     * @return array<string, mixed>
     */
    private function mapPropertyDetail(Property $property, array $fallbackGradients): array
    {
        $base = $this->mapDiscoverProperty($property, $fallbackGradients);
        $owner = $property->owner;
        $currentUser = Auth::user();

        $description = trim((string) $property->description);
        if ($description === '') {
            $description = sprintf(
                '%s berada di area %s, %s. Properti ini dirancang dengan konsep modern dan area komunal yang mendukung aktivitas harian penghuni.',
                $base['title'],
                $base['location'],
                $base['area']
            );
        }

        $reviewsCollection = $property->reviews ?? collect();
        $reviews = $reviewsCollection
            ->map(function ($review) use ($currentUser) {
                $reviewerName = $review->seeker?->name ?? 'Pengguna Koze';
                return [
                    'id' => $review->id,
                    'name' => $reviewerName,
                    'rating' => (int) $review->rating,
                    'text' => $review->comment ?? '',
                    'date' => $review->created_at?->translatedFormat('d M Y'),
                    'createdAt' => $review->created_at?->toIso8601String(),
                    'avatarUrl' => $this->resolveAvatarUrl($review->seeker?->avatar),
                    'isOwn' => $currentUser !== null && $review->seeker_id === $currentUser->id,
                ];
            })
            ->values()
            ->all();

        $ratingCount = $reviewsCollection->count();
        $averageRating = $ratingCount > 0
            ? round((float) $reviewsCollection->avg('rating'), 1)
            : 0;

        $myReview = $currentUser
            ? $reviewsCollection->firstWhere('seeker_id', $currentUser->id)
            : null;

        $canReview = $currentUser !== null && $currentUser->id !== $property->owner_id;
        $reviewBlockReason = null;
        if (!$currentUser) {
            $reviewBlockReason = 'Silakan login dulu untuk memberi ulasan.';
        } elseif ($currentUser->id === $property->owner_id) {
            $reviewBlockReason = 'Pemilik tidak dapat memberi ulasan pada propertinya sendiri.';
        }

        return [
            ...$base,
            'rating' => $averageRating,
            'ratingCount' => $ratingCount,
            'description' => $description,
            'gallery' => $this->buildGallery($property, $fallbackGradients, $base['cover']),
            'owner' => [
                'id' => $owner?->id,
                'name' => $owner?->name ?? 'Pemilik Properti',
                'role' => $this->formatOwnerRole($owner),
                'phone' => $owner?->phone ?? '-',
                'responseTime' => 'Balas chat rata-rata 10 menit',
            ],
            'locationDetail' => $this->buildLocationDetail($property, $base),
            'reviews' => $reviews,
            'reviewsEnabled' => true,
            'canReview' => $canReview,
            'reviewBlockReason' => $reviewBlockReason,
            'myReview' => $myReview ? [
                'id' => $myReview->id,
                'rating' => (int) $myReview->rating,
                'comment' => $myReview->comment ?? '',
            ] : null,
        ];
    }

    private function resolveAvatarUrl(?string $avatar): ?string
    {
        if (!$avatar) {
            return null;
        }
        return str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')
            ? $avatar
            : Storage::url($avatar);
    }

    /**
     * @param array<int, string> $fallbackGradients
     * @return array<int, string>
     */
    private function buildGallery(Property $property, array $fallbackGradients, string $cover): array
    {
        $gallery = [];
        $images = $property->images->values();

        foreach ($images as $index => $image) {
            if (!filled($image->image_url)) {
                continue;
            }

            $imageUrl = $this->resolveImageUrl($image->image_url);
            $gallery[] = $this->buildImageStyle($imageUrl, $index === 0);

            if (count($gallery) >= 4) {
                break;
            }
        }

        if (!$gallery) {
            $gallery[] = $cover;
        }

        if ($cover && $gallery[0] !== $cover) {
            array_unshift($gallery, $cover);
        }

        $fallbackIndex = 0;
        while (count($gallery) < 4) {
            $gallery[] = $fallbackGradients[$fallbackIndex % count($fallbackGradients)];
            $fallbackIndex += 1;
        }

        return array_slice($gallery, 0, 4);
    }

    /**
     * @param array<string, mixed> $base
     * @return array<string, string>
     */
    private function buildLocationDetail(Property $property, array $base): array
    {
        $latitude = $property->latitude !== null
            ? number_format((float) $property->latitude, 6, '.', '')
            : '-';
        $longitude = $property->longitude !== null
            ? number_format((float) $property->longitude, 6, '.', '')
            : '-';

        $address = trim((string) $property->address);
        if ($address === '') {
            $address = sprintf('%s - %s', $base['location'], $base['area']);
        }

        $landmark = $base['location'] !== 'Lokasi belum diisi'
            ? sprintf('Dekat area %s dan fasilitas umum', $base['location'])
            : 'Dekat fasilitas umum';

        return [
            'address' => $address,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'landmark' => $landmark,
        ];
    }

    private function formatOwnerRole(?User $owner): string
    {
        if (!$owner) {
            return 'Pemilik Properti';
        }

        $role = $owner->getRoleNames()->first();

        if (!$role) {
            return 'Pemilik Properti';
        }

        return match (strtolower($role)) {
            'owner' => 'Pemilik Properti',
            'admin' => 'Admin',
            default => $role,
        };
    }

    private function resolveImageUrl(string $imageUrl): string
    {
        return str_starts_with($imageUrl, 'http://') || str_starts_with($imageUrl, 'https://')
            ? $imageUrl
            : Storage::url($imageUrl);
    }

    private function buildImageStyle(string $imageUrl, bool $withOverlay = true): string
    {
        if (!$withOverlay) {
            return "url('{$imageUrl}') center/cover no-repeat";
        }

        return "linear-gradient(140deg, rgba(18,30,45,0.28) 0%, rgba(18,30,45,0.12) 55%, rgba(18,30,45,0.04) 100%), url('{$imageUrl}') center/cover no-repeat";
    }
}
