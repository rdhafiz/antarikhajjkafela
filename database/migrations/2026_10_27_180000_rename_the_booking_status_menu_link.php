<?php

use App\Domain\Menus\MenuRepository;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The booking lookup page is now "Find your booking". Menu links still
 * carrying the original label follow; a label an admin wrote stays.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('menu_items')->where('link_type', 'route')->where('link_target', 'booking_status')->orderBy('id')->each(function ($item) {
            $label = json_decode($item->label ?? '[]', true) ?: [];

            if (($label['en'] ?? null) === 'Check booking status') {
                DB::table('menu_items')->where('id', $item->id)->update([
                    'label' => json_encode(['en' => 'Find your booking', 'bn' => 'আপনার বুকিং খুঁজুন'], JSON_UNESCAPED_UNICODE),
                ]);
            }
        });

        app(MenuRepository::class)->flushAll();
    }

    public function down(): void
    {
        // The new label stays; it can be edited in Menus.
    }
};
