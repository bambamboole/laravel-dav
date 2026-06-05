<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dav_locks', function (Blueprint $table) {
            $table->id();
            $table->text('owner')->nullable();
            $table->unsignedInteger('timeout');
            $table->unsignedInteger('created');
            $table->string('token')->unique();
            $table->unsignedTinyInteger('scope');
            $table->integer('depth');
            $table->text('uri');
            $table->timestamps();

            $table->index('uri');
            $table->index('created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dav_locks');
    }
};
