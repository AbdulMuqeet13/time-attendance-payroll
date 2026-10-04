<?php

namespace App\Http\Controllers\Organisation;

use App\Actions\Organisation\DeleteOrganisationRecord;
use App\Actions\Organisation\SaveOrganisationRecord;
use App\Concerns\FlashesToast;
use App\Enums\PermissionEnum;
use App\Exceptions\Organisation\RecordInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\SaveDesignationRequest;
use App\Models\Designation;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DesignationController extends Controller
{
    use FlashesToast;

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(PermissionEnum::OrganisationManage->value), 403);

        $counts = Employee::query()->active()->selectRaw('designation_id, count(*) as total')->groupBy('designation_id')->pluck('total', 'designation_id');

        return Inertia::render('organisation/designations/index', [
            'designations' => Designation::query()->orderBy('name')->get()
                ->map(fn (Designation $designation) => [...$designation->toArray(), 'employees_count' => (int) ($counts[$designation->id] ?? 0)]),
        ]);
    }

    public function store(SaveDesignationRequest $request, SaveOrganisationRecord $action): RedirectResponse
    {
        $action->handle(new Designation, $request->validated());
        $this->flashSuccess('Designation created.');

        return to_route('designations.index');
    }

    public function update(SaveDesignationRequest $request, Designation $designation, SaveOrganisationRecord $action): RedirectResponse
    {
        $action->handle($designation, $request->validated());
        $this->flashSuccess('Designation updated.');

        return to_route('designations.index');
    }

    public function destroy(Request $request, Designation $designation, DeleteOrganisationRecord $action): RedirectResponse
    {
        abort_unless($request->user()->can(PermissionEnum::OrganisationManage->value), 403);

        try {
            $action->handle($designation, 'designation_id', 'designation');
            $this->flashSuccess('Designation deleted.');
        } catch (RecordInUseException $exception) {
            $this->flashError($exception->getMessage());
        }

        return to_route('designations.index');
    }
}
