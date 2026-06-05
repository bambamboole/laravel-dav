<?php

use Bambamboole\LaravelDav\Facades\Dav;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dav_calendar_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')
                ->constrained(Dav::ownerTable())
                ->cascadeOnDelete();
            $table->string('uri');
            $table->text('source');
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->string('color')->nullable();
            $table->string('refresh_rate')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('strip_todos')->default(false);
            $table->boolean('strip_alarms')->default(false);
            $table->boolean('strip_attachments')->default(false);
            $table->timestamp('last_modified_at')->nullable();
            $table->timestamps();

            $table->unique(['owner_id', 'uri']);
            $table->index(['owner_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dav_calendar_subscriptions');
    }
};
