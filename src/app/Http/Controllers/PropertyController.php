<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Models\Facility;
use App\Models\User;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\PropertyImage;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Traits\UploadTrait;

class PropertyController extends Controller
{
    use UploadTrait;

    public function discoveryMap()
    {
        return Inertia::render('DiscoverMap');
    }

    public function index(Request $request)
    {
        $query = Property::with([
            'images' => fn ($q) => $q->orderByDesc('is_primary')->orderByDesc('created_at'),
            'facilities',
            'tags',
            'propertyType',
        ]);

        /** @var User $user */
        $user = Auth::user();

        // Admin sees all, Owner sees only their own
        if (!$user->hasRole('Admin')) {
            $query->where('owner_id', Auth::id());
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('property_type_id', $request->input('type'));
        }

        $properties = $query->orderBy('created_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        $tags = Tag::all()->pluck('name')->toArray();

        return Inertia::render('Properties/Index', [
            'properties' => $properties,
            'availableTags' => $tags,
            'availableFacilities' => Facility::query()->orderBy('name')->get(['id', 'name', 'icon']),
            'availablePropertyTypes' => PropertyType::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'type']),
        ]);
    }

    public function markers(Request $request)
    {
        $query = Property::query()
            ->where('status', 'active')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($request->filled(['south', 'north', 'west', 'east'])) {
            $south = (float) $request->input('south');
            $north = (float) $request->input('north');
            $west = (float) $request->input('west');
            $east = (float) $request->input('east');

            $query->whereBetween('latitude', [min($south, $north), max($south, $north)]);

            if ($west <= $east) {
                $query->whereBetween('longitude', [$west, $east]);
            } else {
                $query->where(function ($nested) use ($west, $east) {
                    $nested->where('longitude', '>=', $west)
                        ->orWhere('longitude', '<=', $east);
                });
            }
        }

        if ($request->filled('gender')) {
            $genders = explode(',', $request->input('gender'));
            $query->whereIn('gender_restriction', $genders);
        }

        if ($request->filled('price_type')) {
            $types = explode(',', $request->input('price_type'));
            $query->whereIn('price_type', $types);
        }

        if ($request->filled('price_min')) {
            $query->where('price', '>=', (float) $request->input('price_min'));
        }

        if ($request->filled('price_max')) {
            $query->where('price', '<=', (float) $request->input('price_max'));
        }

        if ($request->boolean('available_only')) {
            $query->where('available_rooms', '>', 0);
        }

        if ($request->filled('facilities')) {
            $facilityIds = array_filter(explode(',', $request->input('facilities')), 'is_numeric');
            foreach ($facilityIds as $fid) {
                $query->whereHas('facilities', fn ($q) => $q->where('facilities.id', (int) $fid));
            }
        }

        $limit = min(max((int) $request->input('limit', 80), 20), 160);

        $properties = $query
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->with([
                'images' => fn ($relation) => $relation->orderByDesc('is_primary')->orderByDesc('created_at'),
                'facilities:id,name,icon',
                'propertyType:id,name',
            ])
            ->limit($limit)
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
                    'type' => $property->propertyType?->name,
                    'latitude' => (float) $property->latitude,
                    'longitude' => (float) $property->longitude,
                    'is_featured' => (bool) $property->is_featured,
                    'thumbnail_url' => $primaryImage ? '/storage/' . $primaryImage->image_url : null,
                    'description' => $property->description,
                    'available_rooms' => (int) $property->available_rooms,
                    'total_rooms' => (int) $property->total_rooms,
                    'facilities' => $property->facilities->map(fn ($facility) => [
                        'id' => $facility->id,
                        'name' => $facility->name,
                        'icon' => $facility->icon,
                    ])->values(),
                ];
            })
            ->values();

        return response()->json([
            'data' => $properties,
            'count' => $properties->count(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Properties/Create', [
            'availableTags' => Tag::all()->pluck('name')->toArray(),
            'availableFacilities' => Facility::query()->orderBy('name')->get(['id', 'name', 'icon']),
            'availablePropertyTypes' => PropertyType::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(Property $property)
    {
        if ($property->owner_id !== Auth::id()) {
            abort(403);
        }

        $property->load([
            'images' => fn ($q) => $q->orderByDesc('is_primary')->orderByDesc('created_at'),
            'facilities',
            'tags',
            'propertyType',
        ]);

        return Inertia::render('Properties/Edit', [
            'property' => $property,
            'availableTags' => Tag::all()->pluck('name')->toArray(),
            'availableFacilities' => Facility::query()->orderBy('name')->get(['id', 'name', 'icon']),
            'availablePropertyTypes' => PropertyType::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StorePropertyRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $property = Property::create([
                'owner_id'           => Auth::id(),
                'name'               => $validated['name'],
                'description'        => $validated['description'],
                'property_type_id'   => $validated['property_type_id'],
                'gender_restriction' => $validated['gender_restriction'],
                'price'              => $validated['price'],
                'price_type'         => $validated['price_type'],
                'address'            => $validated['address'],
                'latitude'           => $validated['latitude'] ?? null,
                'longitude'          => $validated['longitude'] ?? null,
                'total_rooms'        => $validated['total_rooms'],
                'available_rooms'    => $validated['available_rooms'],
                'status'             => $validated['status'],
            ]);

            $this->syncTags($property, $request->input('tags', []));
            $this->syncFacilities($property, $request->input('facility_ids', []));

            $thumbnailPath = $this->upload('properties', $request->file('thumbnail'));
            $property->images()->create([
                'image_url'  => $thumbnailPath,
                'is_primary' => true,
            ]);

            if ($request->hasFile('detail_photos')) {
                foreach ($request->file('detail_photos') as $photo) {
                    $path = $this->upload('properties', $photo);
                    $property->images()->create([
                        'image_url'  => $path,
                        'is_primary' => false,
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('properties.index')->with('success', 'Properti berhasil ditambahkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withErrors(['error' => 'Gagal menyimpan properti: ' . $e->getMessage()]);
        }
    }

    public function update(UpdatePropertyRequest $request, Property $property)
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $property->update([
                'name'        => $validated['name'],
                'description' => $validated['description'],
                'property_type_id' => $validated['property_type_id'],
                'gender_restriction' => $validated['gender_restriction'],
                'price'       => $validated['price'],
                'price_type'  => $validated['price_type'],
                'address'     => $validated['address'],
                'latitude'    => $validated['latitude'] ?? null,
                'longitude'   => $validated['longitude'] ?? null,
                'total_rooms' => $validated['total_rooms'],
                'available_rooms' => $validated['available_rooms'],
                'status'      => $validated['status'],
            ]);

            $this->syncTags($property, $request->input('tags', []));
            $this->syncFacilities($property, $request->input('facility_ids', []));

            if ($request->hasFile('new_thumbnail')) {
                $currentThumbnail = $property->images()->where('is_primary', true)->first();
                if ($currentThumbnail) {
                    $this->remove($currentThumbnail->image_url);
                    $currentThumbnail->delete();
                }

                $thumbnailPath = $this->upload('properties', $request->file('new_thumbnail'));
                $property->images()->create([
                    'image_url'  => $thumbnailPath,
                    'is_primary' => true,
                ]);
            }

            if ($request->hasFile('new_detail_photos')) {
                foreach ($request->file('new_detail_photos') as $photo) {
                    $path = $this->upload('properties', $photo);
                    $property->images()->create([
                        'image_url'  => $path,
                        'is_primary' => false,
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('properties.index')->with('success', 'Properti berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withErrors(['error' => 'Gagal memperbarui properti: ' . $e->getMessage()]);
        }
    }

    public function updateStatus(Request $request, Property $property)
    {
        if ($property->owner_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $property->update([
            'status' => $validated['status'],
        ]);

        return redirect()->back()->with('success', 'Status properti berhasil diperbarui.');
    }

    public function destroy(Property $property)
    {
        if ($property->owner_id !== Auth::id()) {
            abort(403);
        }

        foreach ($property->images as $image) {
            $this->remove($image->image_url);
        }

        $property->delete();

        return redirect()->back()->with('success', 'Properti berhasil dihapus.');
    }

    public function destroyImage(Property $property, PropertyImage $image)
    {
        if ($property->owner_id !== Auth::id()) {
            abort(403);
        }

        if ($image->property_id !== $property->id) {
            abort(404);
        }

        if ($image->is_primary) {
            return redirect()->back()->withErrors([
                'error' => 'Foto thumbnail tidak bisa dihapus langsung. Upload thumbnail baru terlebih dahulu.',
            ]);
        }

        $this->remove($image->image_url);
        $image->delete();

        return redirect()->back()->with('success', 'Foto berhasil dihapus.');
    }

    private function syncTags(Property $property, array $tagNames)
    {
        $tagIds = [];
        foreach ($tagNames as $name) {
            $tagName = trim($name);
            if (!empty($tagName)) {
                $tag = Tag::firstOrCreate(['name' => $tagName]);
                $tagIds[] = $tag->id;
            }
        }
        $property->tags()->sync($tagIds);
    }

    private function syncFacilities(Property $property, array $facilityIds)
    {
        $normalizedIds = collect($facilityIds)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $property->facilities()->sync($normalizedIds);
    }
}
