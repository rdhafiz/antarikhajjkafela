<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Staff can create bookings (also from an inquiry): the new bookings.create
 * permission goes to every role that could already update bookings, and an
 * inquiry remembers the booking made from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->after('page_section_id')->constrained('bookings')->nullOnDelete();
        });

        $guard = config('access.guard', 'web');
        $create = Permission::firstOrCreate(['name' => 'bookings.create', 'guard_name' => $guard]);
        $update = Permission::where(['name' => 'bookings.update', 'guard_name' => $guard])->first();
        $update?->roles->each(fn ($role) => $role->givePermissionTo($create));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_id');
        });
    }
};
