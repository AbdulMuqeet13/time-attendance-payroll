<?php

namespace App\Services\Leaves;

use App\Enums\LeaveStatus;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\CarbonImmutable;

/**
 * Yearly leave entitlements. Balances are created on first use: the yearly quota, pro-rated by the
 * months left when someone joins mid-year, plus unused days carried from last year up to the type's limit.
 */
class LeaveBalanceService
{
    public function balanceFor(Employee $employee, LeaveType $type, int $year): LeaveBalance
    {
        return LeaveBalance::query()->firstOrCreate(
            ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year],
            [
                'entitled' => $this->entitlement($employee, $type, $year),
                'carried_forward' => $this->carryForward($employee, $type, $year),
                'adjustment' => 0,
            ],
        );
    }

    /**
     * @return array{total: float, used: float, pending: float, available: float}
     */
    public function summary(Employee $employee, LeaveType $type, int $year, ?int $ignoreRequestId = null): array
    {
        $balance = $this->balanceFor($employee, $type, $year);
        $taken = fn (LeaveStatus $status): float => (float) LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where('status', $status)
            ->whereYear('start_date', $year)
            ->when($ignoreRequestId, fn ($query, int $id) => $query->whereKeyNot($id))
            ->sum('days');

        $used = $taken(LeaveStatus::Approved);
        $pending = $taken(LeaveStatus::Pending);

        return [
            'total' => $balance->total(),
            'used' => $used,
            'pending' => $pending,
            'available' => $balance->total() - $used - $pending,
        ];
    }

    /**
     * Create the year's balances for every active employee (yearly roll-over).
     *
     * @return int The number of balances created
     */
    public function allocateYear(int $year): int
    {
        $created = 0;
        $types = LeaveType::query()->active()->get()->filter->hasQuota();

        Employee::query()->active()->each(function (Employee $employee) use ($types, $year, &$created) {
            foreach ($types as $type) {
                $created += $this->balanceFor($employee, $type, $year)->wasRecentlyCreated ? 1 : 0;
            }
        });

        return $created;
    }

    private function entitlement(Employee $employee, LeaveType $type, int $year): float
    {
        $quota = (float) $type->yearly_quota;
        $joined = CarbonImmutable::parse($employee->joining_date->toDateString());

        if ($joined->year < $year) {
            return $quota;
        }

        if ($joined->year > $year) {
            return 0;
        }

        $monthsRemaining = 12 - $joined->month + 1;

        return floor($quota * $monthsRemaining / 12 * 2) / 2;
    }

    private function carryForward(Employee $employee, LeaveType $type, int $year): float
    {
        $limit = (float) $type->carry_forward_max;

        if ($limit <= 0) {
            return 0;
        }

        $previous = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where('year', $year - 1)
            ->first();

        if (! $previous) {
            return 0;
        }

        $used = (float) LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->approved()
            ->whereYear('start_date', $year - 1)
            ->sum('days');

        return max(0, min($limit, $previous->total() - $used));
    }
}
