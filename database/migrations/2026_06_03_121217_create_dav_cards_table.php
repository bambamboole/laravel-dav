<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dav_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dav_address_book_id')->constrained()->cascadeOnDelete();
            $table->string('uri');
            $table->json('data')->default(json_encode([]));
            $table->string('etag');
            $table->unsignedInteger('size');
            $table->timestamp('last_modified_at');
            $table->longText('card_data');
            $table->timestamps();

            $table->unique(['dav_address_book_id', 'uri']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dav_cards');
    }
};
