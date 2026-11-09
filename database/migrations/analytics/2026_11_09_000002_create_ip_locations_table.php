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
        Schema::create('ip_locations', function (Blueprint $table) {
            $table->string('ip', 45)->primary();
            $table->string('country')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->string('isp', 128)->nullable();
            $table->boolean('is_proxy')->nullable();
            $table->boolean('is_hosting')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->smallInteger('attempts')->default(0);
            $table->timestamps();

            $table->index(['resolved_at', 'failed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_locations');
    }
};
