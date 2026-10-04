<?php

namespace App\Http\Controllers\Employees;

use App\Concerns\FlashesToast;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\StoreEmployeeSalaryRequest;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Services\SalaryService;
use DomainException;
use Illuminate\Http\RedirectResponse;

class EmployeeSalaryController extends Controller
{
    use FlashesToast;

    public function store(StoreEmployeeSalaryRequest $request, Employee $employee, SalaryService $service): RedirectResponse
    {
        $service->record($employee, $request->validated(), $request->user());

        $this->flashSuccess('Salary record saved.');

        return to_route('employees.show', $employee);
    }

    public function destroy(Employee $employee, EmployeeSalary $salary, SalaryService $service): RedirectResponse
    {
        $this->authorize('manageSalary', $employee);

        try {
            $service->delete($salary);
            $this->flashSuccess('Salary record deleted.');
        } catch (DomainException $exception) {
            $this->flashError($exception->getMessage());
        }

        return to_route('employees.show', $employee);
    }
}
