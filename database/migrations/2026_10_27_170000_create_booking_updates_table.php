<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What happened to a booking, as the customer sees it: the booking itself,
 * status changes, payments and messages from the team. Each row records
 * whether the customer was emailed about it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20); // created, status, payment, message
            $table->string('status', 20)->nullable(); // the booking's status after this update
            $table->text('message')->nullable(); // written by staff for the customer
            $table->decimal('amount', 14, 2)->nullable(); // for payments
            $table->timestamp('emailed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['booking_id', 'id']);
        });

        // Bookings made before this start their history with the booking itself.
        DB::table('bookings')->orderBy('id')->each(function ($booking) {
            DB::table('booking_updates')->insert([
                'booking_id' => $booking->id,
                'kind' => 'created',
                'status' => 'pending',
                'created_at' => $booking->created_at,
                'updated_at' => $booking->created_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_updates');
    }
};
