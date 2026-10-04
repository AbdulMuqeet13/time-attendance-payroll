<?php

use App\Enums\AttendanceStatus;
use App\Enums\RoleEnum;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\Shift;
use Inertia\Testing\AssertableInertia as Assert;

test('the attendance summary counts each employee\'s shifts by status', function () {
    $employee = Employee::factory()->create(['joining_date' => '2025-01-01']);
    $shift = Shift::factory()->create();
    AttendanceDay::factory()->for($employee)->for($shift)->status(AttendanceStatus::Late)->create(['date' => '2026-10-05', 'late_minutes' => 20]);
    AttendanceDay::factory()->for($employee)->for($shift)->status(AttendanceStatus::Absent)->create(['date' => '2026-10-06', 'worked_minutes' => 0]);

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->get(route('reports.index', ['report' => 'attendance', 'from' => '2026-10-01', 'to' => '2026-10-31']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/index')
            ->where('result.rows.0.scheduled', 2)
            ->where('result.rows.0.present', 1)
            ->where('result.rows.0.late', 1)
            ->where('result.rows.0.absent', 1));
});

test('reports export to Excel', function () {
    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->get(route('reports.export', ['report' => 'lates', 'from' => '2026-10-01', 'to' => '2026-10-31']))
        ->assertOk()
        ->assertDownload('lates-report-2026-10-01-to-2026-10-31.xlsx');
});

test('employees cannot see reports', function () {
    $this->actingAs(userWithRole(RoleEnum::Employee))->get(route('reports.index'))->assertForbidden();
});
