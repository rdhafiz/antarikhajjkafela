<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nothing is deleted for good: every record the admin can remove is only
 * marked as deleted (and listed in the Trash). Tables with a unique web
 * address, email or name also remember the original value, which is freed
 * for reuse while the record is in the Trash (see Concerns\Trashable).
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'users', 'media', 'menu_items', 'pages', 'page_sections', 'inquiries', 'services', 'packages',
        'package_types', 'package_prices', 'post_categories', 'posts', 'reviews', 'gallery_albums',
        'gallery_items', 'bookings', 'booking_documents', 'payments',
    ];

    /** @var list<string> */
    private const RELEASING = ['users', 'pages', 'services', 'packages', 'post_categories', 'posts', 'gallery_albums'];

    public function up(): void
    {
        foreach ([...self::TABLES, config('permission.table_names.roles', 'roles')] as $table) {
            $releasing = in_array($table, self::RELEASING, true) || $table === config('permission.table_names.roles', 'roles');

            Schema::table($table, function (Blueprint $blueprint) use ($releasing) {
                $blueprint->softDeletes();
                $blueprint->unsignedBigInteger('deleted_by')->nullable();
                if ($releasing) {
                    $blueprint->json('trashed_values')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ([...self::TABLES, config('permission.table_names.roles', 'roles')] as $table) {
            $releasing = in_array($table, self::RELEASING, true) || $table === config('permission.table_names.roles', 'roles');

            Schema::table($table, function (Blueprint $blueprint) use ($releasing) {
                $blueprint->dropSoftDeletes();
                $blueprint->dropColumn($releasing ? ['deleted_by', 'trashed_values'] : ['deleted_by']);
            });
        }
    }
};
