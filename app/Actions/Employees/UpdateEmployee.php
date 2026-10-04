<?php

namespace App\Actions\Employees;

use App\Models\Employee;

class UpdateEmployee
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Employee $employee, array $data): Employee
    {
        $employee->update($data);

        return $employee;
    }
}
