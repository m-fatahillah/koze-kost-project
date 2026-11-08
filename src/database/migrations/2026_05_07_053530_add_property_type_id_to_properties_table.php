<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->foreignId('property_type_id')->nullable()->constrained('property_types')->onDelete('restrict');
        });

        // Migrate data from enum to table
        $properties = DB::table('properties')->get();
        foreach ($properties as $property) {
            $typeLabel = match($property->type) {
                'kos' => 'Kos',
                'kontrakan' => 'Kontrakan',
                default => ucfirst($property->type ?? 'Kos'),
            };

            $typeId = DB::table('property_types')->where('name', $typeLabel)->value('id');
            if (!$typeId) {
                $typeId = DB::table('property_types')->insertGetId([
                    'name' => $typeLabel,
                    'description' => 'Tipe ' . $typeLabel,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('properties')->where('id', $property->id)->update(['property_type_id' => $typeId]);
        }

        // Drop the old column
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->enum('type', ['kos', 'kontrakan'])->default('kos');
        });

        // Revert data
        $properties = DB::table('properties')->get();
        foreach ($properties as $property) {
            $typeName = DB::table('property_types')->where('id', $property->property_type_id)->value('name');
            $enumVal = (strtolower($typeName) === 'kontrakan') ? 'kontrakan' : 'kos';
            DB::table('properties')->where('id', $property->id)->update(['type' => $enumVal]);
        }

        Schema::table('properties', function (Blueprint $table) {
            $table->dropForeign(['property_type_id']);
            $table->dropColumn('property_type_id');
        });
    }
};
