<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * New punches were stored. The attendance engine rebuilds the affected employee days.
 */
class PunchesRecorded
{
    use Dispatchable;

    /**
     * @param  array<int, array<int, string>>  $employeeDates  employee id => list of Y-m-d dates
     */
    public function __construct(public array $employeeDates) {}
}
