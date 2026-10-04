<?php

use App\Enums\RoleEnum;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftAssignment;

test('a shift is assigned to several employees at once', function () {
    $shift = Shift::factory()->create();
    $employees = Employee::factory()->count(3)->create();

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('shift-assignments.store'), [
            'employee_ids' => $employees->pluck('id')->all(),
            'shift_id' => $shift->id,
            'days' => [6, 1, 2, 3, 4, 5],
            'effective_from' => '2026-10-01',
        ])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Shift assigned to 3 employees.']);

    expect(ShiftAssignment::query()->pluck('days')->unique()->values()->all())->toBe([[1, 2, 3, 4, 5, 6]]);
});

test('selecting every weekday is stored as every day', function () {
    $employee = Employee::factory()->create();

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('shift-assignments.store'), [
            'employee_ids' => [$employee->id],
            'shift_id' => Shift::factory()->create()->id,
            'days' => [0, 1, 2, 3, 4, 5, 6],
            'effective_from' => '2026-10-01',
        ]);

    expect(ShiftAssignment::sole()->days)->toBeNull();
});

test('an overlapping assignment is rejected and names the employee', function () {
    $employee = Employee::factory()->create(['name' => 'Sara Khan']);
    ShiftAssignment::factory()->for($employee)->for(Shift::factory()->between('09:00', '17:00'))->create();

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('shift-assignments.store'), [
            'employee_ids' => [$employee->id],
            'shift_id' => Shift::factory()->between('16:00', '22:00')->create()->id,
            'effective_from' => '2026-10-01',
        ])
        ->assertSessionHasErrors(['employee_ids' => 'These employees already have a shift at overlapping times: Sara Khan.']);

    expect(ShiftAssignment::count())->toBe(1);
});

test('editing an assignment does not clash with itself', function () {
    $assignment = ShiftAssignment::factory()->create();

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->put(route('shift-assignments.update', $assignment), [
            'shift_id' => $assignment->shift_id,
            'effective_from' => '2025-01-01',
            'effective_to' => '2026-12-31',
        ])
        ->assertSessionHasNoErrors();

    expect($assignment->fresh()->effective_to->toDateString())->toBe('2026-12-31');
});

test('a branch manager cannot assign shifts to another branch', function () {
    $branch = Branch::factory()->create();
    $manager = userWithRole(RoleEnum::BranchManager, ['branch_id' => $branch->id]);
    $manager->givePermissionTo('shifts.manage');

    $this->actingAs($manager)
        ->post(route('shift-assignments.store'), [
            'employee_ids' => [Employee::factory()->create()->id],
            'shift_id' => Shift::factory()->create()->id,
            'effective_from' => '2026-10-01',
        ])
        ->assertSessionHasErrors(['employee_ids' => 'You can only assign shifts to employees of your branch.']);
});

test('users who can only view shifts cannot assign them', function () {
    $this->actingAs(userWithRole(RoleEnum::BranchManager))
        ->post(route('shift-assignments.store'), [
            'employee_ids' => [Employee::factory()->create()->id],
            'shift_id' => Shift::factory()->create()->id,
            'effective_from' => '2026-10-01',
        ])
        ->assertForbidden();
});

test('a shift still assigned to employees cannot be deleted', function () {
    $assignment = ShiftAssignment::factory()->create();

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->delete(route('shifts.destroy', $assignment->shift))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'This shift is still assigned to employees. End those assignments first.']);

    $this->assertNotSoftDeleted($assignment->shift);
});

test('a shift break must be shorter than the shift', function () {
    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('shifts.store'), [
            'name' => 'Short', 'start_time' => '22:00', 'end_time' => '02:00', 'break_minutes' => 240, 'color' => 'sky',
        ])
        ->assertSessionHasErrors(['break_minutes' => 'The break must be shorter than the shift.']);
});
