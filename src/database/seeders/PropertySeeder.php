<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\User;
use Illuminate\Database\Seeder;

class PropertySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $owners = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'Owner'))
            ->get(['id']);

        if ($owners->isEmpty()) {
            return;
        }

        $facilityIdsByName = Facility::query()->pluck('id', 'name');
        $propertyTypeIdsByName = PropertyType::query()->pluck('id', 'name');

        $propertySeeds = [
            [
                'name' => 'Kos Mentari Kuningan',
                'description' => 'Kos putri nyaman dekat area perkantoran dengan akses transportasi mudah.',
                'property_type' => 'Kos',
                'gender_restriction' => 'putri',
                'price' => 2500000,
                'price_type' => 'monthly',
                'address' => 'Kuningan, Jakarta Selatan',
                'total_rooms' => 20,
                'available_rooms' => 6,
                'facilities' => ['WiFi / Internet', 'AC', 'Kamar Mandi Dalam', 'Parkir Motor'],
            ],
            [
                'name' => 'Urban Nest Tebet',
                'description' => 'Kos putra modern dengan lingkungan aman dan fasilitas lengkap.',
                'property_type' => 'Kos',
                'gender_restriction' => 'putra',
                'price' => 1850000,
                'price_type' => 'monthly',
                'address' => 'Tebet, Jakarta Selatan',
                'total_rooms' => 16,
                'available_rooms' => 3,
                'facilities' => ['WiFi / Internet', 'AC', 'Parkir Motor', 'CCTV'],
            ],
            [
                'name' => 'Griya Kemang Asri',
                'description' => 'Hunian campur premium dengan suasana tenang dan nyaman.',
                'property_type' => 'Kos',
                'gender_restriction' => 'campur',
                'price' => 3200000,
                'price_type' => 'monthly',
                'address' => 'Kemang, Jakarta Selatan',
                'total_rooms' => 24,
                'available_rooms' => 8,
                'facilities' => ['WiFi / Internet', 'AC', 'Kamar Mandi Dalam', 'Parkir Mobil', 'Keamanan 24 Jam'],
            ],
            [
                'name' => 'Kos Citra Senopati',
                'description' => ' putri eksklusif di pusat kota dengan fasilitas harian lengkap.',
                'property_type' => 'Kos',
                'gender_restriction' => 'putri',
                'price' => 4000000,
                'price_type' => 'monthly',
                'address' => 'Senopati, Kebayoran Baru',
                'total_rooms' => 14,
                'available_rooms' => 2,
                'facilities' => ['WiFi / Internet', 'AC', 'Kamar Mandi Dalam', 'Cleaning Service'],
            ],
            [
                'name' => 'Industrial Loft Cilandak',
                'description' => 'Kos putra gaya industrial dengan area komunal yang luas.',
                'property_type' => 'Kos',
                'gender_restriction' => 'putra',
                'price' => 2750000,
                'price_type' => 'monthly',
                'address' => 'Cilandak, Jakarta Selatan',
                'total_rooms' => 18,
                'available_rooms' => 7,
                'facilities' => ['WiFi / Internet', 'Parkir Motor', 'Dapur Umum', 'CCTV'],
            ],
            [
                'name' => 'Zen Living Pejaten',
                'description' => 'Hunian campur dengan konsep tenang untuk mahasiswa dan pekerja.',
                'property_type' => 'Kontrakan',
                'gender_restriction' => 'campur',
                'price' => 2100000,
                'price_type' => 'monthly',
                'address' => 'Pejaten, Pasar Minggu',
                'total_rooms' => 22,
                'available_rooms' => 9,
                'facilities' => ['WiFi / Internet', 'AC', 'Mesin Cuci', 'Parkir Motor'],
            ],
            [
                'name' => 'Smart Stay Ampera',
                'description' => 'Kos putri ramah budget dengan akses cepat ke transportasi publik.',
                'property_type' => 'Kos',
                'gender_restriction' => 'putri',
                'price' => 1950000,
                'price_type' => 'monthly',
                'address' => 'Ampera, Jakarta Selatan',
                'total_rooms' => 15,
                'available_rooms' => 5,
                'facilities' => ['WiFi / Internet', 'AC', 'Kamar Mandi Luar', 'Parkir Motor'],
            ],
            [
                'name' => 'Art Deco Residence',
                'description' => 'Hunian campur dengan desain artistik dan lingkungan asri.',
                'property_type' => 'Apartemen',
                'gender_restriction' => 'campur',
                'price' => 2300000,
                'price_type' => 'monthly',
                'address' => 'Mampang, Jakarta Selatan',
                'total_rooms' => 19,
                'available_rooms' => 4,
                'facilities' => ['WiFi / Internet', 'AC', 'Kamar Mandi Dalam', 'Ruang Tamu'],
            ],
            [
                'name' => 'Riverside House Pancoran',
                'description' => 'Kos putra dengan sirkulasi udara baik dan keamanan 24 jam.',
                'property_type' => 'Kontrakan',
                'gender_restriction' => 'putra',
                'price' => 2400000,
                'price_type' => 'monthly',
                'address' => 'Pancoran, Jakarta Selatan',
                'total_rooms' => 17,
                'available_rooms' => 6,
                'facilities' => ['WiFi / Internet', 'Parkir Motor', 'Keamanan 24 Jam', 'CCTV'],
            ],
            [
                'name' => 'Green Yard Jagakarsa',
                'description' => 'Vila hijau dengan area luas, cocok untuk liburan keluarga.',
                'property_type' => 'Vila',
                'gender_restriction' => 'campur',
                'price' => 2900000,
                'price_type' => 'daily',
                'address' => 'Jagakarsa, Jakarta Selatan',
                'total_rooms' => 20,
                'available_rooms' => 10,
                'facilities' => ['WiFi / Internet', 'AC', 'Kamar Mandi Dalam', 'Parkir Motor', 'Dispenser / Air Minum'],
            ],
        ];

        foreach ($owners as $owner) {
            foreach ($propertySeeds as $index => $seed) {
                $propertyTypeId = $propertyTypeIdsByName[$seed['property_type']] ?? null;

                $property = Property::query()->updateOrCreate(
                    [
                        'owner_id' => $owner->id,
                        'name' => $seed['name'],
                    ],
                    [
                        'description' => $seed['description'],
                        'property_type_id' => $propertyTypeId,
                        'gender_restriction' => $seed['gender_restriction'],
                        'price' => $seed['price'],
                        'price_type' => $seed['price_type'],
                        'address' => $seed['address'],
                        'latitude' => null,
                        'longitude' => null,
                        'total_rooms' => $seed['total_rooms'],
                        'available_rooms' => $seed['available_rooms'],
                        'is_featured' => $index < 3,
                        'status' => 'active',
                    ]
                );

                $facilityIds = collect($seed['facilities'])
                    ->map(fn (string $facilityName) => $facilityIdsByName[$facilityName] ?? null)
                    ->filter()
                    ->values()
                    ->all();

                $property->facilities()->sync($facilityIds);
            }
        }
    }
}
