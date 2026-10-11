<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_categories', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('slug', 120)->unique();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('post_categories')->nullOnDelete();
            $table->json('title');
            $table->string('slug', 180)->unique();
            $table->json('excerpt')->nullable();
            $table->foreignId('banner_id')->nullable()->constrained('media')->nullOnDelete();
            $table->json('author_name')->nullable();
            $table->foreignId('author_image_id')->nullable()->constrained('media')->nullOnDelete();
            $table->json('tags')->nullable();
            $table->json('blocks')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->json('seo')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('intro')->nullable();
            $table->json('text')->nullable();
            $table->string('video_url', 300)->nullable();
            $table->foreignId('photo_id')->nullable()->constrained('media')->nullOnDelete();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('gallery_albums', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->string('slug', 160)->unique();
            $table->json('description')->nullable();
            $table->foreignId('cover_id')->nullable()->constrained('media')->nullOnDelete();
            $table->date('event_date')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('gallery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->constrained('gallery_albums')->cascadeOnDelete();
            $table->string('type', 8);                       // image | video
            $table->foreignId('media_id')->nullable()->constrained('media')->cascadeOnDelete();
            $table->string('video_url', 300)->nullable();
            $table->json('caption')->nullable();
            $table->timestamps();

            $table->index(['album_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_items');
        Schema::dropIfExists('gallery_albums');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('post_categories');
    }
};
