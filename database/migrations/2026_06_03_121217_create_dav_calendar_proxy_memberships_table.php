<?php

use Bambamboole\LaravelDav\Facades\Dav;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dav_calendar_proxy_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')
                ->constrained(Dav::ownerTable())
                ->cascadeOnDelete();
            $table->foreignId('delegate_owner_id')
                ->constrained(Dav::ownerTable())
                ->cascadeOnDelete();
            $table->string('access');
            $table->timestamps();

            $table->unique(['owner_id', 'delegate_owner_id']);
            $table->index(['delegate_owner_id', 'access']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dav_calendar_proxy_memberships');
    }
};
