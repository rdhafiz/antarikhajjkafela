<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When the pilgrim wants to travel: each service decides what the booking
 * form asks (Umrah: month and year; Hajj: the Hajj year; others: nothing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('travel_date', 16)->default('none')->after('is_active');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedTinyInteger('travel_month')->nullable()->after('travelers');
            $table->unsignedSmallInteger('travel_year')->nullable()->after('travel_month');
        });

        // The two services every site starts with.
        DB::table('services')->where('slug', 'hajj')->update(['travel_date' => 'hajj_year']);
        DB::table('services')->where('slug', 'umrah')->update(['travel_date' => 'month_year']);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['travel_month', 'travel_year']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('travel_date');
        });
    }
};
