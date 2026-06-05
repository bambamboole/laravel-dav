<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dav_calendar_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dav_calendar_object_id')
                ->constrained('dav_calendar_objects')
                ->cascadeOnDelete();
            $table->string('managed_id')->unique();
            $table->string('filename')->nullable();
            $table->string('content_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('etag');
            $table->string('storage_disk');
            $table->text('storage_path');
            $table->timestamps();

            $table->index('dav_calendar_object_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dav_calendar_attachments');
    }
};
