<?php

use Database\Seeders\HeroImagesSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * The Civil Aviation licence badge shows its logo on a white plate like the
 * others (filled only where no logo was chosen).
 */
return new class extends Migration
{
    public function up(): void
    {
        (new HeroImagesSeeder)->fillCredentialLogos();
    }

    public function down(): void
    {
        // The logo stays; it can be changed in the page builder.
    }
};
