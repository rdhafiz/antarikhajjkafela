<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff notes on bookings and inquiries: one row per note, with who wrote
 * it and when, instead of a single box that each save overwrote. The text
 * in the old box becomes each record's first note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->morphs('notable');
            $table->text('body');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();
        });

        $now = now();
        foreach ([['bookings', 'admin_notes', 'App\\Models\\Booking'], ['inquiries', 'notes', 'App\\Models\\Inquiry']] as [$table, $column, $type]) {
            DB::table($table)->whereNotNull($column)->where($column, '!=', '')->orderBy('id')->each(function ($row) use ($column, $type, $now) {
                DB::table('notes')->insert([
                    'notable_type' => $type,
                    'notable_id' => $row->id,
                    'body' => $row->{$column},
                    'user_id' => null,
                    'created_at' => $row->updated_at ?? $now,
                    'updated_at' => $row->updated_at ?? $now,
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
