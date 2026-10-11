<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('slug', 120)->unique();
            $table->json('summary')->nullable();
            $table->json('description')->nullable();
            $table->string('icon', 40)->nullable();
            $table->foreignId('image_id')->nullable()->constrained('media')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->json('seo')->nullable();
            $table->timestamps();
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->json('name');
            $table->string('slug', 160)->unique();
            $table->json('summary')->nullable();
            $table->foreignId('banner_id')->nullable()->constrained('media')->nullOnDelete();
            $table->date('booking_starts_on')->nullable();
            $table->date('booking_ends_on')->nullable();
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->string('status', 16)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->string('advance_type', 16)->default('none');    // none | fixed | percentage
            $table->decimal('advance_value', 12, 2)->nullable();
            $table->decimal('min_price', 12, 2)->nullable();          // cached "starting from" price
            $table->json('flights')->nullable();
            $table->json('sections')->nullable();
            $table->json('seo')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'service_id']);
        });

        Schema::create('package_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40)->nullable();
            $table->json('name');
            $table->json('short_description')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('package_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_type_id')->constrained()->cascadeOnDelete();
            $table->json('title');
            $table->json('short_description')->nullable();
            $table->decimal('price', 12, 2);
            $table->string('unit', 24)->default('person');           // person | room | group
            $table->json('hotels')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // Old addresses of records whose slug changed, so links keep working.
        Schema::create('slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->string('old_slug', 160);
            $table->unsignedBigInteger('target_id');
            $table->timestamps();

            $table->unique(['type', 'old_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slug_redirects');
        Schema::dropIfExists('package_prices');
        Schema::dropIfExists('package_types');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('services');
    }
};
