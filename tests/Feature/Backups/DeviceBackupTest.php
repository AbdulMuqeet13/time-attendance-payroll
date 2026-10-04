<?php

use App\Enums\BackupStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\PunchSource;
use App\Enums\RestoreStatus;
use App\Enums\RoleEnum;
use App\Models\AttendancePunch;
use App\Models\BiometricTemplate;
use App\Models\Device;
use App\Models\DeviceBackup;
use App\Models\DeviceCommand;
use App\Models\DeviceEnrollment;
use App\Models\DeviceRestore;
use App\Models\Employee;
use App\Models\User;
use App\Services\Backups\BackupFile;
use App\Services\Backups\DeviceBackupService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
});

function deviceAdmin(): User
{
    return userWithRole(RoleEnum::DeviceAdmin);
}

/**
 * Answer every command delivered on the device's next poll as successful.
 */
function acknowledgeCommands(Device $device): void
{
    $response = test()->get("/iclock/getrequest?SN={$device->serial_number}")->getContent();
    preg_match_all('/^C:(\d+):/m', $response, $matches);

    devicePost("/iclock/devicecmd?SN={$device->serial_number}", collect($matches[1])->map(fn ($id) => "ID={$id}&Return=0&CMD=DATA")->implode("\n"));
}

test('a backup from the device collects what it uploads into an encrypted file', function () {
    $device = Device::factory()->create(['fp_algorithm' => '10']);
    Employee::factory()->create(['device_pin' => '7']);

    $this->actingAs(deviceAdmin())->post(route('backups.store'), [
        'device_id' => $device->id, 'source' => 'device', 'users' => true, 'templates' => true, 'logs' => true,
        'logs_from' => '2026-10-01', 'logs_to' => '2026-10-31',
    ])->assertSessionHasNoErrors();

    expect(DeviceCommand::orderBy('sequence')->pluck('command')->all())->toBe(['CHECK', "DATA QUERY ATTLOG StartTime=2026-10-01 00:00:00\tEndTime=2026-10-31 23:59:59"]);

    acknowledgeCommands($device);
    devicePost("/iclock/cdata?SN={$device->serial_number}&table=OPERLOG", "USER PIN=7\tName=Ali\tPri=0\tPasswd=\tCard=\nUSER PIN=8\tName=Guard\tPri=14\nFP PIN=7\tFID=6\tSize=4\tValid=1\tTMP=QUJD\n");
    devicePost("/iclock/cdata?SN={$device->serial_number}&table=ATTLOG", "7\t2026-10-05 09:00:00\t0\t1\n7\t2026-10-05 17:00:00\t1\t1\n");

    $this->travel(4)->minutes();
    $this->artisan('devices:finalize-backups')->assertSuccessful();

    $backup = DeviceBackup::sole();
    $contents = app(BackupFile::class)->read($backup->file_path, $backup->checksum);

    expect($backup)
        ->status->toBe(BackupStatus::Completed)
        ->counts->toBe(['users' => 2, 'fingerprints' => 1, 'faces' => 0, 'other_templates' => 0, 'logs' => 2])
        ->and($contents['templates'][0])->toMatchArray(['pin' => '7', 'index' => 6, 'template' => 'QUJD', 'major_version' => '10'])
        ->and($backup->records()->count())->toBe(0)
        ->and(Storage::get($backup->file_path))->not->toContain('QUJD');
});

test('a device that never answers gives a failed backup after the timeout', function () {
    $device = Device::factory()->create();
    app(DeviceBackupService::class)->startFromDevice($device, ['users' => true, 'templates' => true]);

    $this->travel(31)->minutes();
    $this->artisan('devices:finalize-backups');

    expect(DeviceBackup::sole())->status->toBe(BackupStatus::Failed)->error->not->toBeNull();
});

test('only one backup of a device runs at a time', function () {
    $device = Device::factory()->create();
    app(DeviceBackupService::class)->startFromDevice($device, []);

    $this->actingAs(deviceAdmin())
        ->post(route('backups.store'), ['device_id' => $device->id, 'source' => 'device'])
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'A backup of this device is already in progress.']);
});

test('a backup from the server uses the employees, templates and scans the app holds for that device', function () {
    $device = Device::factory()->create();
    $employee = Employee::factory()->create(['device_pin' => '7']);
    DeviceEnrollment::factory()->for($employee)->for($device)->create(['status' => EnrollmentStatus::OnDevice]);
    BiometricTemplate::factory()->for($employee)->count(2)->sequence(['finger_index' => 1], ['finger_index' => 6])->create();
    AttendancePunch::factory()->for($employee)->create(['device_id' => $device->id, 'pin' => '7', 'punched_at' => '2026-10-05 09:00']);

    $this->actingAs(deviceAdmin())->post(route('backups.store'), [
        'device_id' => $device->id, 'source' => 'server', 'users' => true, 'templates' => true, 'logs' => true,
        'logs_from' => '2026-10-01', 'logs_to' => '2026-10-31',
    ]);

    expect(DeviceBackup::sole())
        ->status->toBe(BackupStatus::Completed)
        ->counts->toBe(['users' => 1, 'fingerprints' => 2, 'faces' => 0, 'other_templates' => 0, 'logs' => 1]);
});

test('a downloaded backup can be uploaded again, and other files are refused', function () {
    $device = Device::factory()->create();
    app(DeviceBackupService::class)->snapshotFromServer($device, []);
    $backup = DeviceBackup::sole();

    $this->actingAs(deviceAdmin())->get(route('backups.download', $backup))->assertOk()->assertDownload();

    $file = UploadedFile::fake()->createWithContent('backup.zkb', Storage::get($backup->file_path));
    $this->actingAs(deviceAdmin())->post(route('backups.upload'), ['file' => $file])->assertSessionHasNoErrors();

    expect(DeviceBackup::where('mode', 'uploaded')->sole()->device_serial)->toBe($device->serial_number);

    $this->actingAs(deviceAdmin())
        ->post(route('backups.upload'), ['file' => UploadedFile::fake()->createWithContent('other.zkb', 'not a backup')])
        ->assertSessionHasErrors(['file' => 'This file is not a backup from this system, or it was made with a different app key.']);
});

test('restoring to a replacement device wipes it when confirmed, then sends users and compatible templates', function () {
    $old = Device::factory()->create(['fp_algorithm' => '10']);
    $replacement = Device::factory()->create(['fp_algorithm' => '10', 'face_algorithm' => '7']);
    $employee = Employee::factory()->create(['device_pin' => '7', 'name' => 'Ali']);
    DeviceEnrollment::factory()->for($employee)->for($old)->create();
    BiometricTemplate::factory()->for($employee)->create(['finger_index' => 6, 'template' => 'QUJD', 'size' => 4, 'major_version' => '10']);
    BiometricTemplate::factory()->for($employee)->face()->create(['major_version' => '39']);
    app(DeviceBackupService::class)->snapshotFromServer($old, []);

    $this->actingAs(deviceAdmin())
        ->post(route('backups.restore'), ['backup_id' => DeviceBackup::sole()->id, 'target_device_id' => $replacement->id, 'users' => true, 'templates' => true, 'clear_first' => true])
        ->assertSessionHasErrors(['confirmation' => 'Type CLEAR to confirm wiping the device.']);

    $this->actingAs(deviceAdmin())
        ->post(route('backups.restore'), ['backup_id' => DeviceBackup::sole()->id, 'target_device_id' => $replacement->id, 'users' => true, 'templates' => true, 'clear_first' => true, 'confirmation' => 'CLEAR'])
        ->assertSessionHasNoErrors();

    $restore = DeviceRestore::sole();

    expect($replacement->commands()->orderBy('sequence')->pluck('command')->all())->toBe([
        'CLEAR DATA',
        "DATA UPDATE USERINFO PIN=7\tName=Ali\tPri=0\tPasswd=\tCard=",
        "DATA UPDATE FINGERTMP PIN=7\tFID=6\tSize=4\tValid=1\tTMP=QUJD",
    ])->and($restore)->skipped_templates->toBe(1)->status->toBe(RestoreStatus::Running);

    acknowledgeCommands($replacement);

    expect($restore->fresh())->status->toBe(RestoreStatus::Completed)->succeeded_commands->toBe(3);
});

test('restoring from the app pushes every active employee of the device\'s branch', function () {
    $device = Device::factory()->create();
    Employee::factory()->count(2)->create(['branch_id' => $device->branch_id]);

    $this->actingAs(deviceAdmin())
        ->post(route('backups.restore'), ['target_device_id' => $device->id, 'users' => true, 'templates' => true]);

    expect(DeviceRestore::sole()->users_count)->toBe(2);
});

test('importing a backup\'s logs adds only the scans the app is missing', function () {
    $device = Device::factory()->create();
    $employee = Employee::factory()->create(['device_pin' => '7']);
    devicePost("/iclock/cdata?SN={$device->serial_number}&table=ATTLOG", "7\t2026-10-05 09:00:00\t0\t1\n7\t2026-10-05 17:00:00\t1\t1\n");
    app(DeviceBackupService::class)->snapshotFromServer($device, ['logs' => true, 'logs_from' => '2026-10-01', 'logs_to' => '2026-10-31']);
    AttendancePunch::query()->where('punched_at', '2026-10-05 17:00:00')->delete();

    $this->actingAs(deviceAdmin())
        ->post(route('backups.import-logs', DeviceBackup::sole()))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '1 scan(s) were missing and have been added. Attendance is being recalculated; payroll-locked days are unchanged.']);

    expect(AttendancePunch::where('source', PunchSource::Restore)->sole())
        ->employee_id->toBe($employee->id)
        ->punched_at->toDateTimeString()->toBe('2026-10-05 17:00:00');
});

test('old backups beyond the device\'s retention are deleted', function () {
    $device = Device::factory()->create(['backup_retention' => 2]);
    $service = app(DeviceBackupService::class);
    collect(range(1, 4))->each(fn () => $service->snapshotFromServer($device, []));

    expect($service->prune($device))->toBe(2)
        ->and(DeviceBackup::count())->toBe(2);
});

test('HR managers cannot manage backups', function () {
    $this->actingAs(userWithRole(RoleEnum::HrManager))->get(route('backups.index'))->assertForbidden();
});
