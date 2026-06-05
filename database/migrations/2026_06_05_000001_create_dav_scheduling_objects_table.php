<?php

use Bambamboole\LaravelDav\Facades\Dav;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dav_scheduling_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')
                ->constrained(Dav::ownerTable())
                ->cascadeOnDelete();
            $table->string('uri');
            $table->longText('calendar_data');
            $table->string('etag');
            $table->unsignedInteger('size');
            $table->timestamp('last_modified_at');
            $table->timestamps();

            $table->unique(['owner_id', 'uri']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dav_scheduling_objects');
    }
};
