<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AnalyticsMigrate extends Command
{
    protected $signature = 'analytics:migrate {--fresh} {--force}';

    protected $description = 'Create the visit-log tables on the visit-log connection; --fresh rolls back and re-runs only those migrations';

    public function handle(): int
    {
        $options = [
            '--database' => config('visits.connection'),
            '--path' => 'database/migrations/analytics',
            '--force' => (bool) $this->option('force'),
        ];

        // Never migrate:fresh: it drops every table, and the visit log may share the app database.
        if ($this->option('fresh')) {
            $this->call('migrate:reset', $options);
        }

        $status = $this->call('migrate', $options);

        $database = (string) config('database.connections.'.config('visits.connection').'.database');

        self::isSeparate()
            ? $this->info('Visit logs are kept separately, in ['.$database.'].')
            : $this->warn('Visit logs share the application database ['.$database.'].');

        return $status;
    }

    /** Whether the visit log lives in a different database (or on a different host) from the main one. */
    public static function isSeparate(): bool
    {
        $log = config('database.connections.'.config('visits.connection'));
        $main = config('database.connections.'.config('database.default'));

        if (! $log || ! ($log['database'] ?? null)) {
            return false;
        }

        // Two in-memory SQLite connections are two distinct databases, whatever their names say.
        if (($log['driver'] ?? null) === 'sqlite' && $log['database'] === ':memory:') {
            return true;
        }

        return ! ($log['database'] === ($main['database'] ?? null) && ($log['host'] ?? null) === ($main['host'] ?? null));
    }
}
