<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PropertyTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Kos', 'description' => 'Kamar sewa untuk individu, biasanya disewa bulanan atau tahunan.'],
            ['name' => 'Kontrakan', 'description' => 'Rumah kecil atau petakan yang disewa tanpa perabotan.'],
            ['name' => 'Vila', 'description' => 'Rumah liburan yang disewakan untuk jangka pendek atau harian dengan fasilitas lengkap.'],
            ['name' => 'Apartemen', 'description' => 'Unit hunian di dalam gedung bertingkat dengan fasilitas bersama.'],
        ];

        foreach ($types as $type) {
            \App\Models\PropertyType::create($type);
        }
    }
}
