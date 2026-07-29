<?php

namespace App\Support\EmployeeDirectory;

/**
 * One method, one contract: every source of employee master data (the ADHI
 * portal API, a CSV drop, a local fixture) is wrapped behind this so the sync
 * never knows which one is active. Swapping sources is a one-line config change.
 */
interface EmployeeDirectory
{
    /**
     * Fetch every employee row, still in the source's own field names —
     * mapping to users columns is EmployeeSync's job, not the driver's.
     *
     * Must not throw for a transport failure: return an empty array so the
     * sync can report "nothing fetched" instead of dying mid-run.
     *
     * @return array<int,array<string,mixed>>
     */
    public function fetch(): array;
}
