<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seeker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->foreignId('last_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->unsignedInteger('seeker_unread_count')->default(0);
            $table->unsignedInteger('owner_unread_count')->default(0);
            $table->timestamps();

            $table->index(['seeker_id', 'updated_at']);
            $table->index(['owner_id', 'updated_at']);
            $table->unique(['seeker_id', 'owner_id', 'property_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
