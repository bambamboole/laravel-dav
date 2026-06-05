<?php

use Bambamboole\LaravelDav\Facades\Dav;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dav_calendar_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dav_calendar_id')
                ->constrained('dav_calendars')
                ->cascadeOnDelete();
            $table->foreignId('owner_id')
                ->constrained(Dav::ownerTable())
                ->cascadeOnDelete();
            $table->string('uri');
            $table->unsignedTinyInteger('access')->default(1);
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->string('color')->nullable();
            $table->longText('timezone')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('transparent')->default(false);
            $table->string('share_href')->nullable();
            $table->string('share_display_name')->nullable();
            $table->unsignedTinyInteger('share_invite_status')->nullable();
            $table->timestamps();

            $table->unique(['owner_id', 'uri']);
            $table->unique(['dav_calendar_id', 'owner_id']);
            $table->index(['owner_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dav_calendar_instances');
    }
};
