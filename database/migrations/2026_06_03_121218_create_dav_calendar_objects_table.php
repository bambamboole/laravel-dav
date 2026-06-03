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
        Schema::create('dav_calendar_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dav_calendar_id')->constrained()->cascadeOnDelete();
            $table->string('uri');
            $table->string('uid')->nullable();
            $table->string('component_type')->nullable();
            $table->string('summary')->nullable();
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_all_day')->default(false);
            $table->string('timezone')->nullable();
            $table->string('etag');
            $table->unsignedInteger('size');
            $table->timestamp('last_modified_at');
            $table->longText('calendar_data');
            $table->timestamps();

            $table->unique(['dav_calendar_id', 'uri']);
            $table->index(['dav_calendar_id', 'starts_at']);
            $table->index('uid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dav_calendar_objects');
    }
};
