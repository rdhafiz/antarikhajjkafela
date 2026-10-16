<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Roles keep their typed name. "name" stays the slug the permission system
 * and config/access.php key off; "label" is what staff see and rename.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('permission.table_names.roles', 'roles');

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->string('label', 60)->nullable()->after('name');
        });

        // Existing roles keep reading the way they do today until renamed.
        foreach (DB::table($table)->select('id', 'name')->get() as $role) {
            DB::table($table)->where('id', $role->id)->update([
                'label' => config("access.roles.{$role->name}.label") ?? Str::of($role->name)->replace('-', ' ')->title()->toString(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table(config('permission.table_names.roles', 'roles'), function (Blueprint $blueprint) {
            $blueprint->dropColumn('label');
        });
    }
};
