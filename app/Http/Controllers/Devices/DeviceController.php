<?php

namespace App\Http\Controllers\Devices;

use App\Concerns\FlashesToast;
use App\Enums\DeviceCommandStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Devices\DeviceActionRequest;
use App\Http\Requests\Devices\UpdateDeviceRequest;
use App\Models\AttendancePunch;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\DeviceEnrollment;
use App\Models\DeviceUser;
use App\Models\Employee;
use App\Services\Adms\DeviceActions;
use App\Services\Adms\DeviceCommandQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    use FlashesToast;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Device::class);

        $user = $request->user();

        $devices = Device::query()
            ->when($user->branch_id, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->with('branch:id,name')
            ->withCount(['commands as open_commands_count' => fn ($query) => $query->open()])
            ->orderByRaw('branch_id is null desc')
            ->orderBy('name')
            ->get()
            ->map(fn (Device $device) => [...$device->toArray(), 'is_online' => $device->isOnline()]);

        return Inertia::render('devices/index', [
            'devices' => $devices,
            'unmatchedPunchCount' => AttendancePunch::query()->unmatched()->valid()->count(),
            'serverUrl' => url('/'),
            'branches' => Branch::query()->active()
                ->when($user->branch_id, fn ($query, $branchId) => $query->whereKey($branchId))
                ->orderBy('name')->get(['id', 'name']),
            'canManage' => $user->can(PermissionEnum::DevicesManage->value),
        ]);
    }

    public function show(Request $request, Device $device): Response
    {
        $this->authorize('view', $device);

        $device->load('branch:id,name');

        return Inertia::render('devices/show', [
            'device' => [...$device->toArray(), 'is_online' => $device->isOnline(), 'uses_push_v3' => $device->usesPushV3()],
            'commands' => $device->commands()
                ->latest('sequence')
                ->paginate(25, ['id', 'sequence', 'type', 'command', 'status', 'attempts', 'sent_at', 'executed_at', 'return_code', 'created_at'])
                ->through(fn (DeviceCommand $command) => [...$command->toArray(), 'command' => mb_strimwidth($command->command, 0, 140, '…')]),
            'stats' => [
                'punches_today' => $device->punches()->whereDate('punched_at', today())->count(),
                'last_punch_at' => $device->punches()->max('punched_at'),
                'open_commands' => $device->commands()->open()->count(),
                'failed_commands' => $device->commands()->where('status', DeviceCommandStatus::Failed)->count(),
            ],
            'branches' => Branch::query()->active()
                ->when($request->user()->branch_id, fn ($query, $branchId) => $query->whereKey($branchId))
                ->orderBy('name')->get(['id', 'name']),
            'deviceUsers' => Inertia::optional(fn () => $this->reconciliation($device)),
            'employeesWithoutPin' => Inertia::optional(fn () => Employee::query()->visibleTo($request->user())->active()
                ->whereNull('device_pin')->orderBy('name')->get(['id', 'name', 'employee_code'])),
            'can' => [
                'update' => $request->user()->can('update', $device),
                'command' => $request->user()->can('command', $device),
            ],
        ]);
    }

    /**
     * The device's users side by side with the employees who should be on it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function reconciliation(Device $device): array
    {
        $deviceUsers = $device->deviceUsers()->orderBy('pin')->get()->keyBy('pin');
        $employeesByPin = Employee::query()
            ->whereIn('device_pin', $deviceUsers->keys())
            ->get(['id', 'name', 'employee_code', 'device_pin', 'status'])
            ->keyBy('device_pin');

        $rows = $deviceUsers->map(fn (DeviceUser $deviceUser) => [
            'pin' => $deviceUser->pin,
            'device_name' => $deviceUser->name,
            'fingerprint_count' => $deviceUser->fingerprint_count,
            'has_face' => $deviceUser->has_face,
            'privilege' => $deviceUser->privilege,
            'employee' => $employeesByPin->get($deviceUser->pin),
            'state' => $employeesByPin->has($deviceUser->pin) ? 'linked' : 'unknown',
        ])->values();

        $missing = DeviceEnrollment::query()
            ->where('device_id', $device->id)
            ->whereIn('status', [EnrollmentStatus::OnDevice, EnrollmentStatus::Queued, EnrollmentStatus::Failed])
            ->with('employee:id,name,employee_code,device_pin,status')
            ->get()
            ->filter(fn (DeviceEnrollment $enrollment) => $enrollment->employee->device_pin && ! $deviceUsers->has($enrollment->employee->device_pin))
            ->map(fn (DeviceEnrollment $enrollment) => [
                'pin' => $enrollment->employee->device_pin,
                'device_name' => null,
                'fingerprint_count' => 0,
                'has_face' => false,
                'privilege' => 0,
                'employee' => $enrollment->employee,
                'state' => 'missing',
            ])->values();

        return $rows->concat($missing)->all();
    }

    public function update(UpdateDeviceRequest $request, Device $device): RedirectResponse
    {
        $isBeingClaimed = $device->branch_id === null && $request->validated('branch_id') !== null;
        $device->update($request->validated());

        $this->flashSuccess($isBeingClaimed
            ? 'Device claimed. Its scans are recorded from now on.'
            : 'Device updated.');

        return back();
    }

    public function destroy(Device $device): RedirectResponse
    {
        $this->authorize('delete', $device);

        $device->delete();

        $this->flashSuccess('Device removed. Its scans stay in attendance. If it contacts the server again it re-registers as unclaimed.');

        return to_route('devices.index');
    }

    public function action(DeviceActionRequest $request, Device $device, DeviceActions $actions): RedirectResponse
    {
        $this->flashSuccess($actions->run($device, $request->validated('action'), $request->validated(), $request->user()));

        return back();
    }

    public function retryCommand(Device $device, DeviceCommand $command, DeviceCommandQueue $queue): RedirectResponse
    {
        $this->authorize('command', $device);

        $queue->retry($command);
        $this->flashSuccess('Command queued again.');

        return back();
    }

    public function cancelCommand(Device $device, DeviceCommand $command, DeviceCommandQueue $queue): RedirectResponse
    {
        $this->authorize('command', $device);

        $queue->cancel($command);
        $this->flashSuccess('Command cancelled.');

        return back();
    }
}
