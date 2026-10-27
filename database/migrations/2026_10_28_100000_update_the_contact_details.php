<?php

use Illuminate\Database\Migrations\Migration;

/**
 * The agency's new contact details replace the old ones: one phone and
 * WhatsApp number, one email, the new Facebook page and its Messenger link.
 */
return new class extends Migration
{
    public function up(): void
    {
        settings()->set('contact', [
            'primary_phone' => '01827-176727',
            'secondary_phone' => null,
            'primary_whatsapp' => '+880 1827-176727',
            'secondary_whatsapp' => null,
            'primary_email' => 'abdussobhan202@gmail.com',
            'secondary_email' => null,
        ]);

        settings()->set('social', [
            'facebook' => 'https://www.facebook.com/antariktravelinternational/',
            'messenger' => 'https://m.me/antariktravelinternational',
        ]);
    }

    public function down(): void
    {
        // The details stay; they can be changed in Settings.
    }
};
