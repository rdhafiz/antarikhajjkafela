<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16)->index();            // default | custom | campaign
            $table->string('system_key', 32)->nullable()->unique(); // default pages: home, about, ...
            $table->json('title');
            $table->string('slug', 160)->unique();
            $table->string('status', 16)->default('draft'); // draft | published
            $table->json('seo')->nullable();

            // Campaign pages
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('focused_layout')->default(false);
            $table->json('tracking')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('component', 48);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->json('content')->nullable();
            $table->json('style')->nullable();
            $table->timestamps();

            $table->index(['page_id', 'sort']);
        });

        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32)->index();          // contact_form | lead_form | ...
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('page_section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 190)->nullable();
            $table->json('data')->nullable();
            $table->string('status', 16)->default('new')->index(); // new | in_progress | closed
            $table->text('notes')->nullable();
            $table->string('locale', 8)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
        Schema::dropIfExists('page_sections');
        Schema::dropIfExists('pages');
    }
};
