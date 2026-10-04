<?php

use App\Enums\RoleEnum;
use App\Models\Branch;
use App\Models\Employee;

test('guests are redirected to login', function () {
    $this->get(route('branches.index'))->assertRedirect(route('login'));
});

test('users without the organisation permission cannot manage branches', function () {
    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('branches.store'), ['name' => 'Lahore', 'code' => 'LHR'])
        ->assertForbidden();

    $this->assertDatabaseMissing('branches', ['code' => 'LHR']);
});

test('an admin creates a branch', function () {
    $this->actingAs(userWithRole(RoleEnum::SuperAdmin))
        ->post(route('branches.store'), ['name' => 'Lahore Head Office', 'code' => 'LHR', 'is_active' => true])
        ->assertRedirect(route('branches.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Branch created.']);

    $this->assertDatabaseHas('branches', ['name' => 'Lahore Head Office', 'code' => 'LHR']);
});

test('branch codes must be unique', function () {
    Branch::factory()->create(['code' => 'LHR']);

    $this->actingAs(userWithRole(RoleEnum::SuperAdmin))
        ->post(route('branches.store'), ['name' => 'Another', 'code' => 'LHR'])
        ->assertSessionHasErrors(['code' => 'The code has already been taken.']);
});

test('a branch can keep its own code when updated', function () {
    $branch = Branch::factory()->create(['code' => 'LHR']);

    $this->actingAs(userWithRole(RoleEnum::SuperAdmin))
        ->put(route('branches.update', $branch), ['name' => 'Renamed', 'code' => 'LHR', 'is_active' => false])
        ->assertSessionHasNoErrors();

    expect($branch->fresh())->name->toBe('Renamed')->is_active->toBeFalse();
});

test('a branch with employees cannot be deleted', function () {
    $branch = Branch::factory()->create();
    Employee::factory()->for($branch)->create();

    $this->actingAs(userWithRole(RoleEnum::SuperAdmin))
        ->delete(route('branches.destroy', $branch))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'This branch has employees and cannot be deleted. Mark it inactive instead.']);

    $this->assertNotSoftDeleted($branch);
});

test('an empty branch is deleted', function () {
    $branch = Branch::factory()->create();

    $this->actingAs(userWithRole(RoleEnum::SuperAdmin))
        ->delete(route('branches.destroy', $branch))
        ->assertRedirect(route('branches.index'));

    $this->assertSoftDeleted($branch);
});
