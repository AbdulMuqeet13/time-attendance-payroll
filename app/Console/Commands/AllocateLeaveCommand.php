<?php

namespace App\Console\Commands;

use App\Services\Leaves\LeaveBalanceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('leaves:allocate {year? : Year to allocate (default: this year)}')]
#[Description('Create yearly leave balances for active employees, carrying forward unused days')]
class AllocateLeaveCommand extends Command
{
    public function handle(LeaveBalanceService $balances): int
    {
        $year = (int) ($this->argument('year') ?? now()->year);
        $created = $balances->allocateYear($year);

        $this->info("Created {$created} leave balances for {$year}.");

        return self::SUCCESS;
    }
}
