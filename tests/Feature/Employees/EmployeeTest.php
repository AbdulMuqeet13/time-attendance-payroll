<?php

use App\Enums\EmploymentStatus;
use App\Enums\RoleEnum;
use App\Enums\SalaryChangeType;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\SalaryComponent;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, mixed>
 */
function employeePayload(Branch $branch, array $overrides = []): array
{
    return [
        'name' => 'Ali Raza',
        'gender' => 'male',
        'branch_id' => $branch->id,
        'employment_type' => 'permanent',
        'status' => 'active',
        'joining_date' => '2026-03-15',
        'payment_method' => 'cash',
        'device_pin' => ' 101 ',
        'components' => [
            ['salary_component_id' => SalaryComponent::where('name', 'Basic Salary')->value('id'), 'amount' => '50000'],
            ['salary_component_id' => SalaryComponent::where('name', 'House Rent')->value('id'), 'amount' => '10000.50'],
            ['salary_component_id' => SalaryComponent::where('name', 'Medical')->value('id'), 'amount' => ''],
            ['salary_component_id' => SalaryComponent::where('name', 'Income Tax')->value('id'), 'amount' => '1500'],
        ],
        ...$overrides,
    ];
}

test('the employee list only shows a branch manager their own branch', function () {
    $ownBranch = Branch::factory()->create();
    $own = Employee::factory()->for($ownBranch)->create();
    Employee::factory()->create();

    $this->actingAs(userWithRole(RoleEnum::BranchManager, ['branch_id' => $ownBranch->id]))
        ->get(route('employees.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('employees/index')
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $own->id));
});

test('a branch manager cannot open an employee from another branch', function () {
    $manager = userWithRole(RoleEnum::BranchManager, ['branch_id' => Branch::factory()->create()->id]);

    $this->actingAs($manager)
        ->get(route('employees.show', Employee::factory()->create()))
        ->assertForbidden();
});

test('users without the employees permission cannot list employees', function () {
    $this->actingAs(userWithRole(RoleEnum::Employee))
        ->get(route('employees.index'))
        ->assertForbidden();
});

test('creating an employee records an initial salary from the earning and deduction components', function () {
    $branch = Branch::factory()->create();

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('employees.store'), employeePayload($branch))
        ->assertSessionHasNoErrors();

    $employee = Employee::sole();
    $salary = $employee->salaries()->sole();

    expect($employee)
        ->employee_code->toBe('EMP-0001')
        ->device_pin->toBe('101')
        ->and($salary)
        ->change_type->toBe(SalaryChangeType::Initial)
        ->effective_date->toDateString()->toBe('2026-03-15')
        ->gross_salary->toBe('60000.50')
        ->fixed_deductions->toBe('1500.00')
        ->and($salary->components)->toHaveCount(3);
});

test('an employee needs at least one earning amount', function () {
    $payload = employeePayload(Branch::factory()->create(), ['components' => [
        ['salary_component_id' => SalaryComponent::where('name', 'Income Tax')->value('id'), 'amount' => '1500'],
    ]]);

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('employees.store'), $payload)
        ->assertSessionHasErrors(['components' => 'Enter an amount for at least one earning component.']);
});

test('device PINs are unique across employees', function () {
    Employee::factory()->create(['device_pin' => '101']);

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('employees.store'), employeePayload(Branch::factory()->create()))
        ->assertSessionHasErrors(['device_pin' => 'The device pin has already been taken.']);
});

test('a resigned employee needs an exit date', function () {
    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('employees.store'), employeePayload(Branch::factory()->create(), ['status' => EmploymentStatus::Resigned->value]))
        ->assertSessionHasErrors('exit_date');
});

test('a branch manager cannot move an employee to another branch', function () {
    $branch = Branch::factory()->create();
    $employee = Employee::factory()->for($branch)->create();
    $manager = userWithRole(RoleEnum::BranchManager, ['branch_id' => $branch->id]);
    $manager->givePermissionTo('employees.update');

    $this->actingAs($manager)
        ->put(route('employees.update', $employee), [...$employee->toArray(), 'branch_id' => Branch::factory()->create()->id])
        ->assertSessionHasErrors(['branch_id' => 'You can only keep employees in your own branch.']);
});

test('an increment adds a salary record without changing the old one', function () {
    $employee = Employee::factory()->withSalary('50000.00')->create();
    $basic = SalaryComponent::where('name', 'Basic Salary')->value('id');

    $this->actingAs(userWithRole(RoleEnum::PayrollOfficer))
        ->post(route('employees.salaries.store', $employee), [
            'effective_date' => '2026-07-01',
            'change_type' => 'increment',
            'components' => [['salary_component_id' => $basic, 'amount' => '55000']],
        ])
        ->assertSessionHasNoErrors();

    expect($employee->salaries()->pluck('gross_salary')->all())->toBe(['55000.00', '50000.00'])
        ->and($employee->salaryEffectiveOn('2026-06-30')->gross_salary)->toBe('50000.00')
        ->and($employee->salaryEffectiveOn('2026-07-01')->gross_salary)->toBe('55000.00');
});

test('a salary record cannot be added as a second initial record', function () {
    $employee = Employee::factory()->withSalary()->create();

    $this->actingAs(userWithRole(RoleEnum::PayrollOfficer))
        ->post(route('employees.salaries.store', $employee), [
            'effective_date' => '2026-07-01',
            'change_type' => 'initial',
            'components' => [['salary_component_id' => SalaryComponent::where('name', 'Basic Salary')->value('id'), 'amount' => '55000']],
        ])
        ->assertSessionHasErrors('change_type');
});

test("an employee's only salary record cannot be deleted", function () {
    $employee = Employee::factory()->withSalary()->create();
    $salary = $employee->salaries()->sole();

    $this->actingAs(userWithRole(RoleEnum::PayrollOfficer))
        ->delete(route('employees.salaries.destroy', [$employee, $salary]))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => "An employee's only salary record cannot be deleted."]);

    $this->assertModelExists($salary);
});

test('branch managers cannot see salaries on the employee page', function () {
    $employee = Employee::factory()->withSalary()->create();

    $this->actingAs(userWithRole(RoleEnum::BranchManager, ['branch_id' => $employee->branch_id]))
        ->get(route('employees.show', $employee))
        ->assertInertia(fn (Assert $page) => $page->where('salaries', null));
});
