<?php

namespace App\Http\Controllers\Organisation;

use App\Actions\Organisation\SaveOrganisationRecord;
use App\Concerns\FlashesToast;
use App\Enums\PermissionEnum;
use App\Enums\SalaryComponentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\SaveSalaryComponentRequest;
use App\Models\SalaryComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalaryComponentController extends Controller
{
    use FlashesToast;

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(PermissionEnum::SalariesView->value), 403);

        return Inertia::render('organisation/salary-components/index', [
            'salaryComponents' => SalaryComponent::query()->ordered()->get(['id', 'name', 'type', 'sort_order', 'is_active']),
            'componentTypes' => SalaryComponentType::options(),
        ]);
    }

    public function store(SaveSalaryComponentRequest $request, SaveOrganisationRecord $action): RedirectResponse
    {
        $action->handle(new SalaryComponent, $request->validated());
        $this->flashSuccess('Salary component created.');

        return to_route('salary-components.index');
    }

    public function update(SaveSalaryComponentRequest $request, SalaryComponent $salaryComponent, SaveOrganisationRecord $action): RedirectResponse
    {
        $action->handle($salaryComponent, $request->validated());
        $this->flashSuccess('Salary component updated.');

        return to_route('salary-components.index');
    }

    /**
     * Soft deletes the component: existing salary records and payslips keep their amounts and names.
     */
    public function destroy(Request $request, SalaryComponent $salaryComponent): RedirectResponse
    {
        abort_unless($request->user()->can(PermissionEnum::SalariesManage->value), 403);

        $salaryComponent->delete();
        $this->flashSuccess('Salary component deleted.');

        return to_route('salary-components.index');
    }
}
