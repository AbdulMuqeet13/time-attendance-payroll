<?php

namespace App\Http\Controllers\Shifts;

use App\Concerns\FlashesToast;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shifts\SaveRosterOverrideRequest;
use App\Models\RosterOverride;
use App\Services\Attendance\AttendanceRebuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RosterOverrideController extends Controller
{
    use FlashesToast;

    /**
     * Creates or replaces the override for that employee and date.
     */
    public function store(SaveRosterOverrideRequest $request, AttendanceRebuilder $rebuilder): RedirectResponse
    {
        $data = $request->validated();

        RosterOverride::query()->updateOrCreate(
            ['employee_id' => $data['employee_id'], 'date' => $data['date']],
            ['shift_id' => $data['shift_id'] ?? null, 'note' => $data['note'] ?? null, 'created_by' => $request->user()->id],
        );

        $rebuilder->forEmployee((int) $data['employee_id'], $data['date'], $data['date']);
        $this->flashSuccess('Roster updated for that day.');

        return back();
    }

    public function destroy(Request $request, RosterOverride $rosterOverride, AttendanceRebuilder $rebuilder): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->can(PermissionEnum::ShiftsManage->value), 403);
        abort_if($user->branch_id && $rosterOverride->employee->branch_id !== $user->branch_id, 403);

        $rosterOverride->delete();
        $rebuilder->forEmployee($rosterOverride->employee_id, $rosterOverride->date, $rosterOverride->date);

        $this->flashSuccess('Override removed.');

        return back();
    }
}
