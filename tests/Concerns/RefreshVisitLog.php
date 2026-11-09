<?php

namespace Tests\Concerns;

/** Builds the visit-log tables on the analytics connection (a fresh in-memory SQLite per test). */
trait RefreshVisitLog
{
    protected function setUpRefreshVisitLog(): void
    {
        $this->artisan('analytics:migrate', ['--force' => true]);
    }
}
