<?php

namespace App\Http\Controllers\Leaves;

use App\Actions\Organisation\SaveOrganisationRecord;
use App\Concerns\FlashesToast;
use App\Enums\Gender;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeaveTypeController extends Controller
{
    use FlashesToast;

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(PermissionEnum::LeavesView->value), 403);

        return Inertia::render('leaves/types', [
            'leaveTypes' => LeaveType::query()->orderBy('name')->get(),
            'genders' => Gender::options(),
            'canManage' => $request->user()->can(PermissionEnum::SettingsManage->value),
        ]);
    }

    public function store(Request $request, SaveOrganisationRecord $action): RedirectResponse
    {
        $action->handle(new LeaveType, $this->validated($request));
        $this->flashSuccess('Leave type created.');

        return back();
    }

    public function update(Request $request, LeaveType $leaveType, SaveOrganisationRecord $action): RedirectResponse
    {
        $action->handle($leaveType, $this->validated($request, $leaveType));
        $this->flashSuccess('Leave type updated. Existing balances keep their entitlement.');

        return back();
    }

    public function destroy(Request $request, LeaveType $leaveType): RedirectResponse
    {
        abort_unless($request->user()->can(PermissionEnum::SettingsManage->value), 403);

        if (LeaveRequest::query()->where('leave_type_id', $leaveType->id)->exists()) {
            $this->flashError('This leave type has requests. Mark it inactive instead.');
        } else {
            $leaveType->delete();
            $this->flashSuccess('Leave type deleted.');
        }

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?LeaveType $leaveType = null): array
    {
        abort_unless($request->user()->can(PermissionEnum::SettingsManage->value), 403);

        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:10', Rule::unique('leave_types', 'code')->ignore($leaveType)],
            'is_paid' => ['boolean'],
            'yearly_quota' => ['required', 'numeric', 'min:0', 'max:365', 'multiple_of:0.5'],
            'carry_forward_max' => ['required', 'numeric', 'min:0', 'max:365', 'multiple_of:0.5'],
            'allow_half_day' => ['boolean'],
            'requires_attachment' => ['boolean'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'is_active' => ['boolean'],
        ]);
    }
}
