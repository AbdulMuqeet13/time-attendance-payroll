<?php

use App\Enums\RoleEnum;
use Inertia\Testing\AssertableInertia as Assert;

it('renders every list page for a super admin', function (string $route, string $component) {
    $this->actingAs(userWithRole(RoleEnum::SuperAdmin))
        ->get(route($route))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'employees' => ['employees.index', 'employees/index'],
    'add employee' => ['employees.create', 'employees/form'],
    'branches' => ['branches.index', 'organisation/branches/index'],
    'departments' => ['departments.index', 'organisation/departments/index'],
    'designations' => ['designations.index', 'organisation/designations/index'],
    'salary components' => ['salary-components.index', 'organisation/salary-components/index'],
    'company settings' => ['company-settings.edit', 'organisation/settings'],
    'shifts' => ['shifts.index', 'shifts/index'],
    'roster' => ['roster.index', 'shifts/roster'],
    'devices' => ['devices.index', 'devices/index'],
    'unmatched punches' => ['unmatched-punches.index', 'devices/unmatched-punches'],
    'holidays' => ['holidays.index', 'shifts/holidays'],
    'attendance' => ['attendance.index', 'attendance/index'],
    'attendance register' => ['attendance.register', 'attendance/register'],
    'overtime' => ['overtime.index', 'attendance/overtime'],
    'leave requests' => ['leaves.index', 'leaves/index'],
    'leave balances' => ['leave-balances.index', 'leaves/balances'],
    'leave types' => ['leave-types.index', 'leaves/types'],
    'payroll runs' => ['payroll.index', 'payroll/index'],
    'adjustments' => ['adjustments.index', 'payroll/adjustments'],
    'advances' => ['advances.index', 'payroll/advances'],
    'backups' => ['backups.index', 'backups/index'],
    'users' => ['users.index', 'users/index'],
]);
