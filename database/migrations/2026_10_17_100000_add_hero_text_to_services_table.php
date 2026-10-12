<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // Large hero headline and its gold highlight line on the service page (translatable).
            $table->json('headline')->nullable()->after('summary');
            $table->json('highlight')->nullable()->after('headline');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['headline', 'highlight']);
        });
    }
};
