<?php

use Bambamboole\LaravelDav\Facades\Dav;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dav_calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')
                ->constrained(Dav::ownerTable())
                ->cascadeOnDelete();
            $table->json('components')->default(json_encode(['VEVENT', 'VTODO', 'VJOURNAL']));
            $table->unsignedBigInteger('sync_token')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dav_calendars');
    }
};
