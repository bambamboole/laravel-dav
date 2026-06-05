<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dav_properties', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->string('name');
            $table->string('value_type')->default('string');
            $table->longText('value')->nullable();
            $table->timestamps();

            $table->unique(['path', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dav_properties');
    }
};
