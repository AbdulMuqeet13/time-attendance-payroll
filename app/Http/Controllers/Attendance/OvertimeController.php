<?php

namespace App\Http\Controllers\Attendance;

use App\Concerns\FlashesToast;
use App\Enums\PermissionEnum;
use App\Exceptions\Attendance\AttendanceLockedException;
use App\Http\Controllers\Controller;
use App\Models\AttendanceDay;
use App\Services\Attendance\AttendanceAdjustments;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Overtime waiting for approval, and recently decided overtime.
 */
class OvertimeController extends Controller
{
    use FlashesToast;

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(PermissionEnum::OvertimeApprove->value), 403);

        $user = $request->user();
        $visible = fn ($query) => $query->visibleTo($user);
        $with = ['employee:id,name,employee_code', 'shift'];

        return Inertia::render('attendance/overtime', [
            'pending' => AttendanceDay::query()
                ->overtimePending()
                ->whereNull('locked_at')
                ->whereHas('employee', $visible)
                ->with($with)
                ->orderBy('date')
                ->get(),
            'decided' => AttendanceDay::query()
                ->where('overtime_minutes', '>', 0)
                ->whereNotNull('approved_overtime_minutes')
                ->where('date', '>=', now()->subDays(45)->toDateString())
                ->whereHas('employee', $visible)
                ->with($with)
                ->latest('date')
                ->limit(200)
                ->get(),
        ]);
    }

    /**
     * Approve some or all of the overtime (0 rejects it).
     */
    public function decide(Request $request, AttendanceDay $attendanceDay, AttendanceAdjustments $adjustments): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->can(PermissionEnum::OvertimeApprove->value), 403);
        abort_if($user->branch_id && $attendanceDay->employee->branch_id !== $user->branch_id, 403);

        $minutes = $request->validate([
            'approved_minutes' => ['required', 'integer', 'min:0', 'max:'.$attendanceDay->overtime_minutes],
        ])['approved_minutes'];

        try {
            $adjustments->decide(
                $attendanceDay->employee,
                CarbonImmutable::parse($attendanceDay->date->toDateString()),
                $attendanceDay->shift_id,
                ['approved_overtime_minutes' => $minutes],
                $user,
            );
            $this->flashSuccess($minutes === 0 ? 'Overtime rejected.' : 'Overtime approved.');
        } catch (AttendanceLockedException $exception) {
            $this->flashError($exception->getMessage());
        }

        return back();
    }
}
