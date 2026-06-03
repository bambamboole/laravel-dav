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
        Schema::create('dav_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained(config('dav.owner_table', 'users'))
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('secret_hash');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dav_credentials');
    }
};
