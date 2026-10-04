<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::EmployeesView->value);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->can(PermissionEnum::EmployeesView->value) && $this->inScope($user, $employee);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::EmployeesCreate->value);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can(PermissionEnum::EmployeesUpdate->value) && $this->inScope($user, $employee);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->can(PermissionEnum::EmployeesDelete->value) && $this->inScope($user, $employee);
    }

    public function viewSalary(User $user, Employee $employee): bool
    {
        return $user->can(PermissionEnum::SalariesView->value) && $this->inScope($user, $employee);
    }

    public function manageSalary(User $user, Employee $employee): bool
    {
        return $user->can(PermissionEnum::SalariesManage->value) && $this->inScope($user, $employee);
    }

    /**
     * Users tied to a branch may only act on that branch's employees.
     */
    private function inScope(User $user, Employee $employee): bool
    {
        return $user->branch_id === null || $user->branch_id === $employee->branch_id;
    }
}
