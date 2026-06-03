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
        Schema::create('dav_properties', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->string('name');
            $table->longText('value');
            $table->timestamps();

            $table->unique(['path', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dav_properties');
    }
};
