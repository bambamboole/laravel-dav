<?php

use Bambamboole\LaravelDav\Facades\Dav;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dav_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')
                ->constrained(Dav::ownerTable())
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('secret_hash');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dav_credentials');
    }
};
