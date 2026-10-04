<?php

namespace App\Http\Controllers\Shifts;

use App\Actions\Organisation\SaveOrganisationRecord;
use App\Concerns\FlashesToast;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shifts\SaveShiftRequest;
use App\Models\Shift;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    use FlashesToast;

    public function index(Request $request, Settings $settings): Response
    {
        abort_unless($request->user()->can(PermissionEnum::ShiftsView->value), 403);

        $today = now()->toDateString();

        return Inertia::render('shifts/index', [
            'shifts' => Shift::query()
                ->withCount(['assignments as employees_count' => fn ($query) => $query
                    ->where('effective_from', '<=', $today)
                    ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $today))])
                ->orderBy('start_time')
                ->get()
                ->map(fn (Shift $shift) => [
                    ...$shift->toArray(),
                    'scheduled_minutes' => $shift->scheduledMinutes(),
                    'is_overnight' => $shift->isOvernight(),
                ]),
            'defaults' => [
                'late_grace_minutes' => $settings->lateGraceMinutes(),
                'early_window_minutes' => $settings->earlyWindowMinutes(),
                'checkout_grace_minutes' => $settings->checkoutGraceMinutes(),
                'half_day_minutes' => $settings->halfDayMinutes(),
                'min_overtime_minutes' => $settings->minOvertimeMinutes(),
            ],
        ]);
    }

    public function store(SaveShiftRequest $request, SaveOrganisationRecord $action): RedirectResponse
    {
        $action->handle(new Shift, $request->validated());
        $this->flashSuccess('Shift created.');

        return to_route('shifts.index');
    }

    public function update(SaveShiftRequest $request, Shift $shift, SaveOrganisationRecord $action): RedirectResponse
    {
        $action->handle($shift, $request->validated());
        $this->flashSuccess('Shift updated. Attendance already recorded keeps its times until it is rebuilt.');

        return to_route('shifts.index');
    }

    /**
     * Soft deletes the shift. Shifts still assigned to someone (now or later) must be unassigned first.
     */
    public function destroy(Request $request, Shift $shift): RedirectResponse
    {
        abort_unless($request->user()->can(PermissionEnum::ShiftsManage->value), 403);

        $inUse = $shift->assignments()
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', now()->toDateString()))
            ->exists();

        if ($inUse) {
            $this->flashError('This shift is still assigned to employees. End those assignments first.');
        } else {
            $shift->delete();
            $this->flashSuccess('Shift deleted.');
        }

        return to_route('shifts.index');
    }
}
