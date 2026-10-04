<?php

namespace App\Http\Controllers\Organisation;

use App\Actions\Organisation\DeleteOrganisationRecord;
use App\Actions\Organisation\SaveOrganisationRecord;
use App\Concerns\FlashesToast;
use App\Enums\PermissionEnum;
use App\Exceptions\Organisation\RecordInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\SaveBranchRequest;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    use FlashesToast;

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(PermissionEnum::OrganisationManage->value), 403);

        return Inertia::render('organisation/branches/index', [
            'branches' => Branch::query()
                ->withCount(['employees' => fn ($query) => $query->active()])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(SaveBranchRequest $request, SaveOrganisationRecord $action): RedirectResponse
    {
        $action->handle(new Branch, $request->validated());
        $this->flashSuccess('Branch created.');

        return to_route('branches.index');
    }

    public function update(SaveBranchRequest $request, Branch $branch, SaveOrganisationRecord $action): RedirectResponse
    {
        $action->handle($branch, $request->validated());
        $this->flashSuccess('Branch updated.');

        return to_route('branches.index');
    }

    public function destroy(Request $request, Branch $branch, DeleteOrganisationRecord $action): RedirectResponse
    {
        abort_unless($request->user()->can(PermissionEnum::OrganisationManage->value), 403);

        try {
            $action->handle($branch, 'branch_id', 'branch');
            $this->flashSuccess('Branch deleted.');
        } catch (RecordInUseException $exception) {
            $this->flashError($exception->getMessage());
        }

        return to_route('branches.index');
    }
}
