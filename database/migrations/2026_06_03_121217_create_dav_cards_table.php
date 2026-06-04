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
            $table->string('uid')->nullable();
            $table->string('full_name')->nullable();
            $table->string('given_name')->nullable();
            $table->string('family_name')->nullable();
            $table->string('organization')->nullable();
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
            $table->json('emails')->default(json_encode([]));
            $table->json('phones')->default(json_encode([]));
            $table->json('phone_numbers')->default(json_encode([]));
            $table->json('email_addresses')->default(json_encode([]));
            $table->json('addresses')->default(json_encode([]));
            $table->json('urls')->default(json_encode([]));
            $table->json('instant_messages')->default(json_encode([]));
            $table->json('social_profiles')->default(json_encode([]));
            $table->json('dates')->default(json_encode([]));
            $table->json('relations')->default(json_encode([]));
            $table->json('vcard_extensions')->default(json_encode([]));
            $table->string('etag');
            $table->unsignedInteger('size');
            $table->timestamp('last_modified_at');
            $table->longText('card_data');
            $table->timestamps();

            $table->unique(['dav_address_book_id', 'uri']);
            $table->index('full_name');
            $table->index('uid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dav_cards');
    }
};
