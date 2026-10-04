<?php

namespace App\Http\Controllers\Shifts;

use App\Actions\Organisation\SaveOrganisationRecord;
use App\Concerns\FlashesToast;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shifts\SaveHolidayRequest;
use App\Models\Branch;
use App\Models\Holiday;
use App\Services\Attendance\AttendanceRebuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HolidayController extends Controller
{
    use FlashesToast;

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(PermissionEnum::ShiftsView->value), 403);

        $year = $request->integer('year', now()->year);

        return Inertia::render('shifts/holidays', [
            'year' => $year,
            'holidays' => Holiday::query()
                ->whereYear('date', $year)
                ->with('branch:id,name')
                ->orderBy('date')
                ->get(),
            'branches' => Branch::query()->active()->orderBy('name')->get(['id', 'name']),
            'canManage' => $request->user()->can(PermissionEnum::HolidaysManage->value),
        ]);
    }

    public function store(SaveHolidayRequest $request, SaveOrganisationRecord $action, AttendanceRebuilder $rebuilder): RedirectResponse
    {
        $holiday = $action->handle(new Holiday, $request->validated());
        $rebuilder->forBranch($holiday->branch_id, $holiday->date->addDay(), $holiday->date);
        $this->flashSuccess('Holiday added.');

        return back();
    }

    public function update(SaveHolidayRequest $request, Holiday $holiday, SaveOrganisationRecord $action, AttendanceRebuilder $rebuilder): RedirectResponse
    {
        $previous = [$holiday->branch_id, $holiday->date];
        $action->handle($holiday, $request->validated());
        $rebuilder->forBranch($previous[0], $previous[1]->addDay(), $previous[1]);
        $rebuilder->forBranch($holiday->branch_id, $holiday->date->addDay(), $holiday->date);
        $this->flashSuccess('Holiday updated.');

        return back();
    }

    public function destroy(Request $request, Holiday $holiday, AttendanceRebuilder $rebuilder): RedirectResponse
    {
        abort_unless($request->user()->can(PermissionEnum::HolidaysManage->value), 403);

        $holiday->delete();
        $rebuilder->forBranch($holiday->branch_id, $holiday->date->addDay(), $holiday->date);
        $this->flashSuccess('Holiday deleted.');

        return back();
    }
}
