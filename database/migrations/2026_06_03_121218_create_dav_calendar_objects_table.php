<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dav_calendar_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dav_calendar_id')->constrained()->cascadeOnDelete();
            $table->string('uri');
            $table->string('uid')->nullable();
            $table->string('component_type')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_all_day')->default(false);
            $table->boolean('recurs')->default(false);
            $table->string('timezone')->nullable();
            $table->json('data')->default(json_encode([]));
            $table->string('etag');
            $table->unsignedInteger('size');
            $table->timestamp('last_modified_at');
            $table->longText('calendar_data');
            $table->timestamps();

            $table->unique(['dav_calendar_id', 'uri']);
            $table->index(['dav_calendar_id', 'starts_at']);
            $table->index(['dav_calendar_id', 'component_type', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dav_calendar_objects');
    }
};
