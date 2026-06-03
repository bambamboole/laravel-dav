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
        Schema::table('dav_cards', function (Blueprint $table) {
            $table->string('contact_type')->default('person');
            $table->string('name_prefix')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('previous_family_name')->nullable();
            $table->string('name_suffix')->nullable();
            $table->string('nickname')->nullable();
            $table->string('phonetic_given_name')->nullable();
            $table->string('phonetic_middle_name')->nullable();
            $table->string('phonetic_family_name')->nullable();
            $table->string('job_title')->nullable();
            $table->string('department')->nullable();
            $table->string('phonetic_organization')->nullable();
            $table->text('note')->nullable();
            $table->json('birthday')->nullable();
            $table->json('pronouns')->default(json_encode([]));
            $table->json('phone_numbers')->default(json_encode([]));
            $table->json('email_addresses')->default(json_encode([]));
            $table->json('addresses')->default(json_encode([]));
            $table->json('urls')->default(json_encode([]));
            $table->json('instant_messages')->default(json_encode([]));
            $table->json('social_profiles')->default(json_encode([]));
            $table->json('dates')->default(json_encode([]));
            $table->json('relations')->default(json_encode([]));
            $table->json('vcard_extensions')->default(json_encode([]));
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dav_cards', function (Blueprint $table) {
            $table->dropColumn([
                'contact_type',
                'name_prefix',
                'middle_name',
                'previous_family_name',
                'name_suffix',
                'nickname',
                'phonetic_given_name',
                'phonetic_middle_name',
                'phonetic_family_name',
                'job_title',
                'department',
                'phonetic_organization',
                'note',
                'birthday',
                'pronouns',
                'phone_numbers',
                'email_addresses',
                'addresses',
                'urls',
                'instant_messages',
                'social_profiles',
                'dates',
                'relations',
                'vcard_extensions',
            ]);
        });
    }
};
