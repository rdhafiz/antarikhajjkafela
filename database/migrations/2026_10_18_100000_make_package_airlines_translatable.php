<?php

use App\Support\Locales;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The flight "airline" field became translatable: plain strings become
 * {base locale: value}, so the admin editor shows them in every language tab.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->convert(fn ($airline) => is_string($airline) ? [app(Locales::class)->base() => $airline] : $airline);
    }

    public function down(): void
    {
        $base = app(Locales::class)->base();

        $this->convert(fn ($airline) => is_array($airline) ? ($airline[$base] ?? collect($airline)->first(fn ($v) => filled($v))) : $airline);
    }

    private function convert(callable $map): void
    {
        DB::table('packages')->whereNotNull('flights')->orderBy('id')->each(function ($package) use ($map) {
            $flights = json_decode($package->flights, true);

            if (! is_array($flights)) {
                return;
            }

            foreach ($flights as $i => $flight) {
                if (array_key_exists('airline', $flight)) {
                    $flights[$i]['airline'] = $map($flight['airline']);
                }
            }

            DB::table('packages')->where('id', $package->id)->update(['flights' => json_encode($flights, JSON_UNESCAPED_UNICODE)]);
        });
    }
};
