<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 24)->unique();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('package_price_id')->nullable()->constrained()->nullOnDelete();
            // What was booked, frozen at booking time: names, unit and price.
            $table->json('snapshot');
            $table->decimal('unit_price', 12, 2);
            $table->unsignedSmallInteger('travelers');
            $table->unsignedSmallInteger('quantity');
            $table->decimal('total', 12, 2);
            $table->string('advance_type', 16)->default('none');
            $table->decimal('advance_value', 12, 2)->nullable();
            $table->decimal('advance_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('BDT');
            $table->string('status', 20)->default('pending');
            $table->string('customer_name', 120);
            $table->string('customer_phone', 20);
            $table->string('customer_email', 190)->nullable();
            $table->string('customer_address', 300)->nullable();
            $table->text('customer_note')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('locale', 8)->default('en');
            $table->string('ip', 45)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('customer_phone');
        });

        Schema::create('booking_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);                     // passport | nid | other
            $table->string('path');
            $table->string('original_name', 190);
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('method', 24);                   // sslcommerz | dgepay | bank | mobile_banking | cash | other
            $table->boolean('is_online')->default(false);
            $table->string('status', 16);                   // initiated | paid | failed | cancelled
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('BDT');
            $table->string('transaction_id', 64)->nullable()->unique();
            $table->string('gateway_reference', 120)->nullable();
            $table->string('reference', 120)->nullable();   // offline: bank / mobile banking reference
            $table->text('note')->nullable();
            $table->json('log')->nullable();                // gateway requests and responses (secrets removed)
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('booking_documents');
        Schema::dropIfExists('bookings');
    }
};
