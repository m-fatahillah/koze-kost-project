
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('advertisements', function (Blueprint $table) {
            $table->id();
            $table->string('title')->comment('Nama kampanye');
            $table->string('advertiser_name')->comment('Nama pihak pengiklan');
            $table->string('image_url')->comment('Banner iklan');
            $table->string('target_url')->comment('URL tujuan klik');
            $table->enum('placement', ['header', 'sidebar', 'interstitial', 'detail']);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['active', 'inactive', 'completed'])->default('active');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('advertisements');
    }
};