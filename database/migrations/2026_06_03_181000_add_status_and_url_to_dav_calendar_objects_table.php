<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dav_calendar_objects', function (Blueprint $table): void {
            $table->string('status')->nullable()->after('location');
            $table->string('url')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('dav_calendar_objects', function (Blueprint $table): void {
            $table->dropColumn(['status', 'url']);
        });
    }
};
