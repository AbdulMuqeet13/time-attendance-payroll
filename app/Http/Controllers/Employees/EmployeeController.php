<?php

namespace App\Http\Controllers\Employees;

use App\Actions\Employees\DeleteEmployee;
use App\Actions\Employees\UpdateEmployee;
use App\Concerns\FlashesToast;
use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\PaymentMethod;
use App\Enums\PermissionEnum;
use App\Enums\SalaryChangeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\StoreEmployeeRequest;
use App\Http\Requests\Employees\UpdateEmployeeRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Device;
use App\Models\Employee;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    use FlashesToast;

    private const SORTABLE = ['employee_code', 'name', 'joining_date', 'created_at'];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        $request->validate([
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $user = $request->user();

        $employees = Employee::query()
            ->visibleTo($user)
            ->with(['branch:id,name', 'department:id,name', 'designation:id,name'])
            ->when($user->can('salaries.view'), fn ($query) => $query->with('currentSalary'))
            ->search($request->string('search')->toString() ?: null)
            ->when($request->input('branch_id'), fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($request->input('department_id'), fn ($query, $departmentId) => $query->where('department_id', $departmentId))
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderBy($request->input('sort', 'employee_code'), $request->input('direction', 'asc'))
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return Inertia::render('employees/index', [
            'employees' => $employees,
            'filters' => $request->only(['search', 'branch_id', 'department_id', 'status']),
            ...$this->lookups($user),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Employee::class);

        return Inertia::render('employees/form', [
            'employee' => null,
            'nextEmployeeCode' => app(EmployeeService::class)->nextEmployeeCode(),
            'salaryComponents' => SalaryComponent::query()->active()->ordered()->get(['id', 'name', 'type']),
            ...$this->lookups($request->user()),
            'managers' => $this->managerOptions($request->user()),
        ]);
    }

    public function store(StoreEmployeeRequest $request, EmployeeService $service): RedirectResponse
    {
        $employee = $service->create($request->validated(), $request->user());

        $this->flashSuccess("Employee {$employee->name} created.");

        return to_route('employees.show', $employee);
    }

    public function show(Request $request, Employee $employee): Response
    {
        $this->authorize('view', $employee);

        $user = $request->user();
        $canViewSalary = $user->can('viewSalary', $employee);

        $employee->load(['branch:id,name', 'department:id,name', 'designation:id,name', 'manager:id,name,employee_code', 'user:id,email']);

        return Inertia::render('employees/show', [
            'employee' => $employee,
            'salaries' => $canViewSalary
                ? $employee->salaries()->with(['components.salaryComponent:id,name,type', 'creator:id,name'])->get()
                : null,
            'currentSalaryId' => $canViewSalary ? $employee->salaryEffectiveOn(now())?->id : null,
            'salaryComponents' => fn () => $user->can('manageSalary', $employee)
                ? SalaryComponent::query()->active()->ordered()->get(['id', 'name', 'type'])
                : [],
            'salaryChangeTypes' => array_values(array_filter(
                SalaryChangeType::options(),
                fn (array $option) => $option['value'] !== SalaryChangeType::Initial->value,
            )),
            'biometrics' => $user->can(PermissionEnum::BiometricsManage->value) ? [
                'templates' => $employee->biometricTemplates()->with('sourceDevice:id,name')->orderBy('type')->orderBy('finger_index')->get(),
                'enrollments' => $employee->deviceEnrollments()->with('device:id,name,serial_number')->get(),
                'devices' => Device::query()->visibleTo($user)->claimed()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'serial_number', 'last_seen_at']),
            ] : null,
            'can' => [
                'update' => $user->can('update', $employee),
                'delete' => $user->can('delete', $employee),
                'manageSalary' => $user->can('manageSalary', $employee),
            ],
        ]);
    }

    public function edit(Request $request, Employee $employee): Response
    {
        $this->authorize('update', $employee);

        return Inertia::render('employees/form', [
            'employee' => $employee,
            'nextEmployeeCode' => null,
            'salaryComponents' => [],
            ...$this->lookups($request->user()),
            'managers' => $this->managerOptions($request->user(), $employee),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee, UpdateEmployee $action): RedirectResponse
    {
        $action->handle($employee, $request->validated());

        $this->flashSuccess('Employee updated.');

        return to_route('employees.show', $employee);
    }

    public function destroy(Employee $employee, DeleteEmployee $action): RedirectResponse
    {
        $this->authorize('delete', $employee);

        $action->handle($employee);

        $this->flashSuccess('Employee deleted.');

        return to_route('employees.index');
    }

    /**
     * Select options shared by the list filters and the employee form.
     *
     * @return array<string, mixed>
     */
    private function lookups(User $user): array
    {
        return [
            'branches' => Branch::query()->active()
                ->when($user->branch_id, fn ($query, $branchId) => $query->whereKey($branchId))
                ->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->active()->orderBy('name')->get(['id', 'name']),
            'designations' => Designation::query()->active()->orderBy('name')->get(['id', 'name']),
            'genders' => Gender::options(),
            'employmentTypes' => EmploymentType::options(),
            'employmentStatuses' => EmploymentStatus::options(),
            'paymentMethods' => PaymentMethod::options(),
        ];
    }

    /**
     * @return Collection<int, Employee>
     */
    private function managerOptions(User $user, ?Employee $except = null): Collection
    {
        return Employee::query()->visibleTo($user)->active()
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code']);
    }
}
