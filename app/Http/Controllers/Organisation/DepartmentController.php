<?php

namespace App\Http\Controllers\Organisation;

use App\Actions\Organisation\DeleteOrganisationRecord;
use App\Actions\Organisation\SaveOrganisationRecord;
use App\Concerns\FlashesToast;
use App\Enums\PermissionEnum;
use App\Exceptions\Organisation\RecordInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\SaveDepartmentRequest;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    use FlashesToast;

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(PermissionEnum::OrganisationManage->value), 403);

        $counts = Employee::query()->active()->selectRaw('department_id, count(*) as total')->groupBy('department_id')->pluck('total', 'department_id');

        return Inertia::render('organisation/departments/index', [
            'departments' => Department::query()->orderBy('name')->get()
                ->map(fn (Department $department) => [...$department->toArray(), 'employees_count' => (int) ($counts[$department->id] ?? 0)]),
        ]);
    }

    public function store(SaveDepartmentRequest $request, SaveOrganisationRecord $action): RedirectResponse
    {
        $action->handle(new Department, $request->validated());
        $this->flashSuccess('Department created.');

        return to_route('departments.index');
    }

    public function update(SaveDepartmentRequest $request, Department $department, SaveOrganisationRecord $action): RedirectResponse
    {
        $action->handle($department, $request->validated());
        $this->flashSuccess('Department updated.');

        return to_route('departments.index');
    }

    public function destroy(Request $request, Department $department, DeleteOrganisationRecord $action): RedirectResponse
    {
        abort_unless($request->user()->can(PermissionEnum::OrganisationManage->value), 403);

        try {
            $action->handle($department, 'department_id', 'department');
            $this->flashSuccess('Department deleted.');
        } catch (RecordInUseException $exception) {
            $this->flashError($exception->getMessage());
        }

        return to_route('departments.index');
    }
}
