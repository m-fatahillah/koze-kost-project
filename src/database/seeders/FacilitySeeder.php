<?php

namespace Database\Seeders;

use App\Models\Facility;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class FacilitySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $facilities = [
            // Kamar / Furnitur
            ['name' => 'Kamar Mandi Dalam', 'icon' => 'fluent-emoji-flat:bathtub'],
            ['name' => 'Kamar Mandi Luar', 'icon' => 'fluent-emoji-flat:bathtub'],
            ['name' => 'AC', 'icon' => 'fluent-emoji-flat:snowflake'],
            ['name' => 'Kipas Angin', 'icon' => 'fluent-emoji-flat:wind-face'],
            ['name' => 'Kasur', 'icon' => 'fluent-emoji-flat:bed'],
            ['name' => 'Lemari Pakaian', 'icon' => 'fluent-emoji-flat:t-shirt'],
            ['name' => 'Meja Belajar', 'icon' => 'fluent-emoji-flat:couch-and-lamp'],
            ['name' => 'Kursi', 'icon' => 'fluent-emoji-flat:chair'],
            ['name' => 'TV', 'icon' => 'fluent-emoji-flat:television'],
            
            // Fasilitas Umum
            ['name' => 'WiFi / Internet', 'icon' => 'fluent-emoji-flat:wireless'],
            ['name' => 'Dapur Umum', 'icon' => 'fluent-emoji-flat:kitchen-knife'],
            ['name' => 'Kulkas Umum', 'icon' => 'fluent-emoji-flat:ice'],
            ['name' => 'Mesin Cuci', 'icon' => 'fluent-emoji-flat:soap'],
            ['name' => 'Jemuran', 'icon' => 'fluent-emoji-flat:sun'],
            ['name' => 'Ruang Tamu', 'icon' => 'fluent-emoji-flat:busts-in-silhouette'],
            ['name' => 'Dispenser / Air Minum', 'icon' => 'fluent-emoji-flat:sweat-droplets'],
            
            // Parkir
            ['name' => 'Parkir Motor', 'icon' => 'fluent-emoji-flat:motorcycle'],
            ['name' => 'Parkir Mobil', 'icon' => 'fluent-emoji-flat:sport-utility-vehicle'],
            
            // Listrik & Air
            ['name' => 'Listrik Termasuk', 'icon' => 'fluent-emoji-flat:high-voltage'],
            ['name' => 'Listrik Token', 'icon' => 'fluent-emoji-flat:electric-plug'],
            ['name' => 'Bebas Biaya Air', 'icon' => 'fluent-emoji-flat:droplet'],
            
            // Keamanan & Aturan
            ['name' => 'Keamanan 24 Jam', 'icon' => 'fluent-emoji-flat:three-oclock'],
            ['name' => 'CCTV', 'icon' => 'fluent-emoji-flat:cinema'],
            ['name' => 'Akses Kunci 24 Jam', 'icon' => 'fluent-emoji-flat:old-key'],
            ['name' => 'Boleh Pasutri', 'icon' => 'fluent-emoji-flat:japanese-dolls'],
            ['name' => 'Boleh Bawa Hewan', 'icon' => 'fluent-emoji-flat:cat'],
            ['name' => 'Cleaning Service', 'icon' => 'fluent-emoji-flat:broom'],
        ];

        foreach ($facilities as $facility) {
            Facility::create($facility);
        }
    }
}
