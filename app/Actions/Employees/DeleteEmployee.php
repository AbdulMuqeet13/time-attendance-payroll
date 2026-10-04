<?php

namespace App\Actions\Employees;

use App\Models\Employee;

class DeleteEmployee
{
    /**
     * Soft deletes the employee; attendance and payroll history stay intact.
     */
    public function handle(Employee $employee): void
    {
        $employee->delete();
    }
}
