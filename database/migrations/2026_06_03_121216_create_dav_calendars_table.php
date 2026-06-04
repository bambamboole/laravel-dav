<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dav_calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained(config('dav.owner_table', 'users'))
                ->cascadeOnDelete();
            $table->string('uri');
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->string('color')->nullable();
            $table->string('timezone')->nullable();
            $table->json('components')->default(json_encode(['VEVENT', 'VTODO', 'VJOURNAL']));
            $table->unsignedBigInteger('sync_token')->default(1);
            $table->timestamps();

            $table->unique(['user_id', 'uri']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dav_calendars');
    }
};
