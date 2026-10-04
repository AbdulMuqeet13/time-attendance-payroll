<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\Attendance\AttendanceProcessor;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('attendance:rebuild
    {from? : First date (default: yesterday)}
    {to? : Last date (default: today)}
    {--employee=* : Only these employee ids}
    {--branch= : Only this branch id}')]
#[Description('Rebuild attendance days from punches, roster, holidays and leave (payroll-locked days are skipped)')]
class RebuildAttendanceCommand extends Command
{
    public function handle(AttendanceProcessor $processor): int
    {
        $from = CarbonImmutable::parse($this->argument('from') ?? 'yesterday');
        $to = CarbonImmutable::parse($this->argument('to') ?? 'today');

        $employees = Employee::query()
            ->when($this->option('employee'), fn ($query, array $ids) => $query->whereKey($ids))
            ->when($this->option('branch'), fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->employedBetween($from, $to);

        $rows = 0;
        $count = 0;

        $employees->chunkById(100, function ($chunk) use ($processor, $from, $to, &$rows, &$count) {
            foreach ($chunk as $employee) {
                $rows += $processor->rebuild($employee, $from, $to);
                $count++;
            }
        });

        $this->info("Rebuilt {$rows} attendance rows for {$count} employees ({$from->toDateString()} to {$to->toDateString()}).");

        return self::SUCCESS;
    }
}
