<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Booking emails now go out just after the response, so an update can be
 * "to be emailed" for a moment before it is "emailed": notify records the
 * intent, emailed_at the send.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_updates', function (Blueprint $table) {
            $table->boolean('notify')->default(false)->after('amount');
        });

        DB::table('booking_updates')->whereNotNull('emailed_at')->update(['notify' => true]);
    }

    public function down(): void
    {
        Schema::table('booking_updates', function (Blueprint $table) {
            $table->dropColumn('notify');
        });
    }
};
