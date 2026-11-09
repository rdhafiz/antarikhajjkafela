<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['bookings', 'inquiries'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->uuid('visitor_id')->nullable()->index();
                $table->uuid('session_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['bookings', 'inquiries'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['visitor_id']);
                $table->dropIndex(['session_id']);
                $table->dropColumn(['visitor_id', 'session_id']);
            });
        }
    }
};
