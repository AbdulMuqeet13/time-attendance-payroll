<?php

use App\Enums\RoleEnum;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRun;

test('a bonus as a percentage of basic salary is given to everyone in a department', function () {
    $department = Department::factory()->create();
    $first = Employee::factory()->withSalary('50000.00')->create(['department_id' => $department->id]);
    $second = Employee::factory()->withSalary('80000.00')->create(['department_id' => $department->id]);
    Employee::factory()->withSalary('60000.00')->create();

    $this->actingAs(userWithRole(RoleEnum::PayrollOfficer))
        ->post(route('adjustments.store'), [
            'kind' => 'bonus', 'name' => 'Eid bonus', 'period' => '2026-10', 'amount_type' => 'percent', 'amount' => 50,
            'percent_of' => 'basic', 'scope' => 'group', 'department_id' => $department->id,
        ])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Adjustment added for 2 employees.']);

    expect(PayrollAdjustment::orderBy('employee_id')->pluck('amount', 'employee_id')->all())
        ->toBe([$first->id => '25000.00', $second->id => '40000.00']);
});

test('an adjustment paid by an approved payroll cannot be removed', function () {
    $adjustment = PayrollAdjustment::factory()->create(['payroll_run_id' => PayrollRun::query()->create([
        'reference' => 'PR-X', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'status' => 'approved',
    ])->id]);

    $this->actingAs(userWithRole(RoleEnum::PayrollOfficer))
        ->delete(route('adjustments.destroy', $adjustment))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'This was paid in an approved payroll and cannot be removed.']);
});

test('HR managers can see but not add adjustments', function () {
    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('adjustments.store'), ['kind' => 'bonus'])
        ->assertForbidden();
});
