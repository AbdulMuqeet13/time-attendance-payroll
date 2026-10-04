<?php

namespace App\Http\Controllers\Leaves;

use App\Concerns\FlashesToast;
use App\Enums\LeaveStatus;
use App\Enums\PermissionEnum;
use App\Exceptions\Leaves\LeaveException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leaves\StoreLeaveRequestRequest;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Leaves\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveRequestController extends Controller
{
    use FlashesToast;

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(PermissionEnum::LeavesView->value), 403);

        $user = $request->user();

        $requests = LeaveRequest::query()
            ->whereHas('employee', fn ($query) => $query->visibleTo($user)
                ->search($request->string('search')->toString() ?: null)
                ->when($request->input('branch_id'), fn ($query, $branchId) => $query->where('branch_id', $branchId)))
            ->when($request->input('status', 'pending'), fn ($query, $status) => $status === 'all' ? $query : $query->where('status', $status))
            ->when($request->input('leave_type_id'), fn ($query, $typeId) => $query->where('leave_type_id', $typeId))
            ->with(['employee:id,name,employee_code,branch_id', 'leaveType:id,name,code,is_paid', 'requester:id,name', 'decider:id,name'])
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->latest('start_date')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return Inertia::render('leaves/index', [
            'requests' => $requests,
            'counts' => LeaveRequest::query()->whereHas('employee', fn ($query) => $query->visibleTo($user))
                ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'leaveTypes' => LeaveType::query()->active()->orderBy('name')->get(['id', 'name', 'code', 'is_paid', 'allow_half_day', 'requires_attachment']),
            'branches' => Branch::query()->active()
                ->when($user->branch_id, fn ($query, $branchId) => $query->whereKey($branchId))
                ->orderBy('name')->get(['id', 'name']),
            'statuses' => LeaveStatus::options(),
            'employees' => fn () => Employee::query()->visibleTo($user)->active()->orderBy('name')->get(['id', 'name', 'employee_code']),
            'can' => [
                'manage' => $user->can(PermissionEnum::LeavesManage->value),
                'approve' => $user->can(PermissionEnum::LeavesApprove->value),
            ],
        ]);
    }

    public function store(StoreLeaveRequestRequest $request, LeaveService $leaves): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('leave-attachments');
        }

        $autoApprove = $request->boolean('approve') && $request->user()->can(PermissionEnum::LeavesApprove->value);

        try {
            $leaves->create($request->employee(), $data, $request->user(), $autoApprove);
            $this->flashSuccess($autoApprove ? 'Leave recorded and approved.' : 'Leave request submitted for approval.');
        } catch (LeaveException $exception) {
            $this->flashError($exception->getMessage());
        }

        return back();
    }

    public function approve(Request $request, LeaveRequest $leaveRequest, LeaveService $leaves): RedirectResponse
    {
        $this->authorizeDecision($request->user(), $leaveRequest);

        return $this->attempt(fn () => $leaves->approve($leaveRequest, $request->user(), $request->input('note')), 'Leave approved. Attendance for those days is updated.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest, LeaveService $leaves): RedirectResponse
    {
        $this->authorizeDecision($request->user(), $leaveRequest);
        $note = $request->validate(['note' => ['required', 'string', 'max:500']])['note'];

        return $this->attempt(fn () => $leaves->reject($leaveRequest, $request->user(), $note), 'Leave rejected.');
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest, LeaveService $leaves): RedirectResponse
    {
        $user = $request->user();
        $isOwn = $leaveRequest->employee->user_id === $user->id && $leaveRequest->status === LeaveStatus::Pending;

        if (! $isOwn) {
            $this->authorizeDecision($user, $leaveRequest);
        }

        return $this->attempt(fn () => $leaves->cancel($leaveRequest, $user, $request->input('note')), 'Leave cancelled.');
    }

    public function attachment(Request $request, LeaveRequest $leaveRequest): StreamedResponse
    {
        $user = $request->user();
        $allowed = $leaveRequest->employee->user_id === $user->id
            || ($user->can(PermissionEnum::LeavesView->value) && ($user->branch_id === null || $user->branch_id === $leaveRequest->employee->branch_id));

        abort_unless($allowed && $leaveRequest->attachment_path, 404);

        return Storage::download($leaveRequest->attachment_path);
    }

    private function authorizeDecision(User $user, LeaveRequest $leaveRequest): void
    {
        abort_unless($user->can(PermissionEnum::LeavesApprove->value), 403);
        abort_if($user->branch_id && $leaveRequest->employee->branch_id !== $user->branch_id, 403);
    }

    private function attempt(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
            $this->flashSuccess($success);
        } catch (LeaveException $exception) {
            $this->flashError($exception->getMessage());
        }

        return back();
    }
}
