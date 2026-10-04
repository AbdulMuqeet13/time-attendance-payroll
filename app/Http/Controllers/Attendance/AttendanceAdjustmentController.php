<?php

namespace App\Http\Controllers\Attendance;

use App\Concerns\FlashesToast;
use App\Enums\PermissionEnum;
use App\Exceptions\Attendance\AttendanceLockedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreAttendanceDecisionRequest;
use App\Http\Requests\Attendance\StoreManualPunchRequest;
use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Services\Attendance\AttendanceAdjustments;
use App\Services\Attendance\AttendanceRebuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttendanceAdjustmentController extends Controller
{
    use FlashesToast;

    public function storePunch(StoreManualPunchRequest $request, AttendanceAdjustments $adjustments): RedirectResponse
    {
        try {
            $adjustments->addPunch($request->employee(), CarbonImmutable::parse($request->validated('punched_at')), $request->validated('reason'), $request->user());
            $this->flashSuccess('Punch added and attendance updated.');
        } catch (AttendanceLockedException $exception) {
            $this->flashError($exception->getMessage());
        }

        return back();
    }

    public function voidPunch(Request $request, AttendancePunch $punch, AttendanceAdjustments $adjustments): RedirectResponse
    {
        abort_unless($request->user()->can(PermissionEnum::AttendanceManage->value), 403);
        abort_if($punch->employee === null, 404);
        $this->authorize('view', $punch->employee);

        $reason = $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'];

        try {
            $adjustments->voidPunch($punch, $reason, $request->user());
            $this->flashSuccess('Punch discarded and attendance updated.');
        } catch (AttendanceLockedException $exception) {
            $this->flashError($exception->getMessage());
        }

        return back();
    }

    public function decide(StoreAttendanceDecisionRequest $request, AttendanceDay $attendanceDay, AttendanceAdjustments $adjustments): RedirectResponse
    {
        $adjustments->decide(
            $attendanceDay->employee,
            CarbonImmutable::parse($attendanceDay->date->toDateString()),
            $attendanceDay->shift_id,
            [
                'status' => $request->validated('status'),
                'waive_late' => $request->boolean('waive_late'),
                'reason' => $request->validated('reason'),
            ],
            $request->user(),
        );

        $this->flashSuccess('Attendance updated.');

        return back();
    }

    /**
     * Rebuild attendance for a period, e.g. after changing shift rules or the roster.
     */
    public function rebuild(Request $request, AttendanceRebuilder $rebuilder): RedirectResponse
    {
        abort_unless($request->user()->can(PermissionEnum::AttendanceManage->value), 403);

        $data = $request->validate([
            'from' => ['required', 'date', 'before_or_equal:today'],
            'to' => ['required', 'date', 'after_or_equal:from', 'before_or_equal:today'],
        ]);

        $rebuilder->forBranch($request->user()->branch_id, CarbonImmutable::parse($data['from'])->addDay(), $data['to']);

        $this->flashSuccess('Rebuild queued. Days included in an approved payroll are left as they are.');

        return back();
    }
}
