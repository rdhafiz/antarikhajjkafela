<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('visits.connection');
    }

    public function up(): void
    {
        Schema::create('page_visits', function (Blueprint $table) {
            $table->id();
            $table->uuid('visitor_id');
            $table->uuid('session_id');
            $table->string('subject_type', 16);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('path', 512);
            $table->string('locale', 5)->nullable();
            $table->string('referrer', 2048)->nullable();
            $table->string('channel', 24)->nullable();
            $table->string('source', 64)->nullable();
            $table->string('referrer_host', 255)->nullable();
            $table->string('in_app', 32)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('device_type', 16)->nullable();
            $table->string('platform', 32)->nullable();
            $table->string('browser', 32)->nullable();
            $table->boolean('is_bot')->default(false);
            $table->string('utm_source', 255)->nullable();
            $table->string('utm_medium', 255)->nullable();
            $table->string('utm_campaign', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['subject_type', 'subject_id', 'created_at'], 'pv_subject_created');
            $table->index(['is_bot', 'created_at']);
            $table->index(['channel', 'created_at']);
            $table->index(['source', 'created_at']);
            $table->index('session_id');
            $table->index('visitor_id');
            $table->index('ip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_visits');
    }
};
