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
        Schema::create('dav_changes', function (Blueprint $table) {
            $table->id();
            $table->string('collection_type');
            $table->unsignedBigInteger('collection_id');
            $table->string('resource_uri')->nullable();
            $table->unsignedTinyInteger('operation');
            $table->unsignedBigInteger('sync_token');
            $table->timestamp('created_at');

            $table->index(['collection_type', 'collection_id', 'sync_token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dav_changes');
    }
};
