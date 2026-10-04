<?php

use App\Enums\RoleEnum;
use App\Models\Employee;
use App\Models\User;

test('an admin creates a branch manager tied to a branch', function () {
    $employee = Employee::factory()->create(['user_id' => null]);

    $this->actingAs(userWithRole(RoleEnum::SuperAdmin))
        ->post(route('users.store'), [
            'name' => 'Sana Manager', 'email' => 'sana@example.com', 'password' => 'secret-pass-123', 'password_confirmation' => 'secret-pass-123',
            'roles' => ['Branch Manager'], 'branch_id' => $employee->branch_id, 'employee_id' => $employee->id,
        ])
        ->assertSessionHasNoErrors();

    $user = User::where('email', 'sana@example.com')->sole();

    expect($user->hasRole('Branch Manager'))->toBeTrue()
        ->and($user->branch_id)->toBe($employee->branch_id)
        ->and($employee->fresh()->user_id)->toBe($user->id);
});

test('only users with the users permission manage accounts', function () {
    $this->actingAs(userWithRole(RoleEnum::HrManager))->get(route('users.index'))->assertForbidden();
});

test('admins cannot deactivate themselves', function () {
    $admin = userWithRole(RoleEnum::SuperAdmin);

    $this->actingAs($admin)
        ->put(route('users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'roles' => ['Super Admin'], 'is_active' => false])
        ->assertSessionHasErrors(['roles' => 'You cannot remove your own admin access or deactivate yourself.']);
});

test('a deactivated user cannot sign in', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $this->assertGuest();
});

test('a user deactivated while signed in is signed out', function () {
    $user = userWithRole(RoleEnum::HrManager);
    $this->actingAs($user);
    $user->update(['is_active' => false]);

    $this->get(route('employees.index'))->assertRedirect(route('login'));
    $this->assertGuest();
});
