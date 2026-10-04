<?php

namespace App\Http\Controllers\Backups;

use App\Concerns\FlashesToast;
use App\Enums\PermissionEnum;
use App\Exceptions\Backups\BackupException;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceBackup;
use App\Models\DeviceRestore;
use App\Models\User;
use App\Services\Backups\DeviceBackupService;
use App\Services\Backups\DeviceRestoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeviceBackupController extends Controller
{
    use FlashesToast;

    public function index(Request $request): Response
    {
        $user = $this->authorizeBackups($request);
        $deviceIds = Device::query()->visibleTo($user)->pluck('id');
        $scoped = fn ($query) => $user->branch_id ? $query->whereIn('device_id', $deviceIds) : $query;

        return Inertia::render('backups/index', [
            'backups' => DeviceBackup::query()
                ->tap($scoped)
                ->when($request->input('device_id'), fn ($query, $deviceId) => $query->where('device_id', $deviceId))
                ->with(['device:id,name', 'creator:id,name'])
                ->latest('id')
                ->limit(200)
                ->get(),
            'restores' => DeviceRestore::query()
                ->whereIn('target_device_id', $deviceIds)
                ->with(['targetDevice:id,name', 'backup:id,device_name,created_at', 'creator:id,name'])
                ->latest('id')
                ->limit(50)
                ->get(),
            'devices' => Device::query()->visibleTo($user)->claimed()->orderBy('name')
                ->get(['id', 'name', 'serial_number', 'last_seen_at', 'fp_algorithm', 'face_algorithm', 'push_version', 'is_active']),
            'filters' => $request->only('device_id'),
        ]);
    }

    /**
     * Back up a device, either by asking the device itself or from what the app already holds.
     */
    public function store(Request $request, DeviceBackupService $backups): RedirectResponse
    {
        $user = $this->authorizeBackups($request);

        $data = $request->validate([
            'device_id' => ['required', 'integer'],
            'source' => ['required', Rule::in(['device', 'server'])],
            'users' => ['boolean'],
            'templates' => ['boolean'],
            'logs' => ['boolean'],
            'logs_from' => ['required_if_accepted:logs', 'nullable', 'date'],
            'logs_to' => ['required_if_accepted:logs', 'nullable', 'date', 'after_or_equal:logs_from'],
        ]);

        $device = Device::query()->visibleTo($user)->findOrFail((int) $data['device_id']);

        try {
            if ($data['source'] === 'device') {
                $backups->startFromDevice($device, $data, $user);
                $this->flashSuccess('Backup started. The device uploads its data over the next few minutes; the file is ready once it goes quiet.');
            } else {
                $backups->snapshotFromServer($device, $data, $user);
                $this->flashSuccess('Backup created from the data held by the app.');
            }
        } catch (BackupException $exception) {
            $this->flashError($exception->getMessage());
        }

        return back();
    }

    public function upload(Request $request, DeviceBackupService $backups): RedirectResponse
    {
        $user = $this->authorizeBackups($request);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:102400'],
            'device_id' => ['nullable', 'integer'],
        ]);

        $device = isset($data['device_id']) ? Device::query()->visibleTo($user)->findOrFail((int) $data['device_id']) : null;

        try {
            $backup = $backups->upload((string) file_get_contents($request->file('file')->getRealPath()), $device, $user);
            $this->flashSuccess("Backup of {$backup->device_name} uploaded.");
        } catch (BackupException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        return back();
    }

    public function download(Request $request, DeviceBackup $deviceBackup): StreamedResponse
    {
        $this->authorizeBackup($request, $deviceBackup);
        abort_unless($deviceBackup->hasFile() && Storage::exists($deviceBackup->file_path), 404);

        return Storage::download(
            $deviceBackup->file_path,
            Str::slug($deviceBackup->device_name).'-'.$deviceBackup->created_at->format('Y-m-d-His').'.zkb',
        );
    }

    public function destroy(Request $request, DeviceBackup $deviceBackup, DeviceBackupService $backups): RedirectResponse
    {
        $this->authorizeBackup($request, $deviceBackup);

        $backups->delete($deviceBackup);
        $this->flashSuccess('Backup deleted.');

        return back();
    }

    /**
     * Add the backup's attendance logs to the app and recalculate attendance (scans already stored are skipped).
     */
    public function importLogs(Request $request, DeviceBackup $deviceBackup, DeviceBackupService $backups): RedirectResponse
    {
        $this->authorizeBackup($request, $deviceBackup);

        try {
            $count = $backups->importLogs($deviceBackup);
            $this->flashSuccess("{$count} scan(s) were missing and have been added. Attendance is being recalculated; payroll-locked days are unchanged.");
        } catch (BackupException $exception) {
            $this->flashError($exception->getMessage());
        }

        return back();
    }

    /**
     * Put users and templates on a device from a backup, or from the app's current employees.
     */
    public function restore(Request $request, DeviceRestoreService $restores): RedirectResponse
    {
        $user = $this->authorizeBackups($request);

        $data = $request->validate([
            'backup_id' => ['nullable', 'integer', Rule::exists('device_backups', 'id')],
            'target_device_id' => ['required', 'integer'],
            'users' => ['boolean'],
            'templates' => ['boolean'],
            'clear_first' => ['boolean'],
            'confirmation' => [Rule::requiredIf($request->boolean('clear_first')), 'nullable', 'in:CLEAR'],
        ], ['confirmation.in' => 'Type CLEAR to confirm wiping the device.', 'confirmation.required' => 'Type CLEAR to confirm wiping the device.']);

        $target = Device::query()->visibleTo($user)->findOrFail((int) $data['target_device_id']);
        $backup = isset($data['backup_id']) ? DeviceBackup::query()->findOrFail((int) $data['backup_id']) : null;

        if ($backup) {
            $this->authorizeBackup($request, $backup);
        }

        try {
            $restore = $restores->start($backup, $target, $data, $user);
            $this->flashSuccess("Restoring {$restore->users_count} user(s) and {$restore->templates_count} template(s) to {$target->name}."
                .($restore->skipped_templates > 0 ? " {$restore->skipped_templates} template(s) skipped: made by a different algorithm." : ''));
        } catch (BackupException $exception) {
            $this->flashError($exception->getMessage());
        }

        return back();
    }

    private function authorizeBackups(Request $request): User
    {
        abort_unless($request->user()->can(PermissionEnum::BackupsManage->value), 403);

        return $request->user();
    }

    private function authorizeBackup(Request $request, DeviceBackup $backup): void
    {
        $user = $this->authorizeBackups($request);

        if ($user->branch_id) {
            abort_unless($backup->device_id && Device::query()->visibleTo($user)->whereKey($backup->device_id)->exists(), 403);
        }
    }
}
