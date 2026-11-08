<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            // Beritahu Laravel kalau sender_id merujuk ke tabel 'users'
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();

            // Beritahu Laravel kalau receiver_id juga merujuk ke tabel 'users'
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();

            $table->text('content');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
