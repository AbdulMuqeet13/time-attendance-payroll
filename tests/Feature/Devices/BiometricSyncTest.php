<?php

use App\Enums\BiometricType;
use App\Enums\DeviceCommandType;
use App\Enums\EmploymentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\RoleEnum;
use App\Models\BiometricTemplate;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\DeviceEnrollment;
use App\Models\Employee;
use App\Services\Biometrics\BiometricSyncService;
use Illuminate\Support\Facades\DB;

test('fingerprints uploaded by a device are stored encrypted under the employee with that PIN', function () {
    $device = Device::factory()->create(['fp_algorithm' => '10']);
    $employee = Employee::factory()->create(['device_pin' => '7']);

    devicePost("/iclock/cdata?SN={$device->serial_number}&table=OPERLOG", "FP PIN=7\tFID=6\tSize=8\tValid=1\tTMP=QUJDREVGR0g=\n");

    $template = BiometricTemplate::sole();

    expect($template)
        ->employee_id->toBe($employee->id)
        ->type->toBe(BiometricType::Fingerprint)
        ->finger_index->toBe(6)
        ->template->toBe('QUJDREVGR0g=')
        ->major_version->toBe('10')
        ->and(DB::table('biometric_templates')->value('template'))->not->toBe('QUJDREVGR0g=')
        ->and(DeviceEnrollment::sole()->status)->toBe(EnrollmentStatus::OnDevice);
});

test('a face uploaded in the BIODATA table is stored with its algorithm version', function () {
    $device = Device::factory()->create();
    Employee::factory()->create(['device_pin' => '7']);

    devicePost(
        "/iclock/cdata?SN={$device->serial_number}&table=BIODATA",
        "BIODATA Pin=7\tNo=0\tIndex=0\tValid=1\tDuress=0\tType=9\tMajorVer=39\tMinorVer=1\tFormat=0\tTmp=RkFDRQ==\n",
    );

    expect(BiometricTemplate::sole())
        ->type->toBe(BiometricType::Face)
        ->storage->toBe('biodata')
        ->biodata_type->toBe(9)
        ->major_version->toBe('39');
});

test('pushing an employee to another device sends the user and their templates', function () {
    $device = Device::factory()->create(['fp_algorithm' => '10', 'face_algorithm' => '39']);
    $employee = Employee::factory()->create(['device_pin' => '7', 'name' => 'Ali Raza']);
    BiometricTemplate::factory()->for($employee)->create(['template' => 'QUJD', 'size' => 4, 'finger_index' => 6]);
    BiometricTemplate::factory()->for($employee)->face()->create(['template' => 'RkFDRQ==']);

    $this->actingAs(userWithRole(RoleEnum::DeviceAdmin))
        ->post(route('employees.biometrics.push', $employee), ['device_ids' => [$device->id]])
        ->assertSessionHasNoErrors();

    expect(DeviceCommand::orderBy('sequence')->pluck('command')->all())->toBe([
        "DATA UPDATE USERINFO PIN=7\tName=Ali Raza\tPri=0\tPasswd=\tCard=",
        "DATA UPDATE FINGERTMP PIN=7\tFID=6\tSize=4\tValid=1\tTMP=QUJD",
        "DATA UPDATE BIODATA Pin=7\tNo=0\tIndex=0\tValid=1\tDuress=0\tType=9\tMajorVer=39\tMinorVer=0\tFormat=0\tTmp=RkFDRQ==",
    ])->and(DeviceEnrollment::sole()->status)->toBe(EnrollmentStatus::Queued);
});

test('templates made by a different algorithm are not sent to the device', function () {
    $device = Device::factory()->create(['fp_algorithm' => '12']);
    $employee = Employee::factory()->create(['device_pin' => '7']);
    BiometricTemplate::factory()->for($employee)->create(['major_version' => '10']);

    $this->actingAs(userWithRole(RoleEnum::DeviceAdmin))
        ->post(route('employees.biometrics.push', $employee), ['device_ids' => [$device->id]]);

    expect(DeviceCommand::pluck('type')->all())->toBe([DeviceCommandType::UserUpdate])
        ->and(DeviceEnrollment::sole()->message)->toBe('1 template(s) skipped: made by a different algorithm than this device uses.');
});

test('the enrollment is on the device once every command succeeds, and failed if any fails', function (string $secondResult, EnrollmentStatus $expected) {
    $device = Device::factory()->create();
    $employee = Employee::factory()->create(['device_pin' => '7']);
    BiometricTemplate::factory()->for($employee)->create();
    app(BiometricSyncService::class)->push($employee, $device);
    $this->get("/iclock/getrequest?SN={$device->serial_number}");

    devicePost("/iclock/devicecmd?SN={$device->serial_number}", "ID=1&Return=0&CMD=DATA\nID=2&Return={$secondResult}&CMD=DATA\n");

    expect(DeviceEnrollment::sole()->status)->toBe($expected);
})->with([
    'all succeed' => ['0', EnrollmentStatus::OnDevice],
    'one fails' => ['-1003', EnrollmentStatus::Failed],
]);

test('an employee without a device PIN cannot be pushed', function () {
    $employee = Employee::factory()->create(['device_pin' => null]);

    $this->actingAs(userWithRole(RoleEnum::DeviceAdmin))
        ->post(route('employees.biometrics.push', $employee), ['device_ids' => [Device::factory()->create()->id]])
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => "{$employee->name} has no device PIN. Set one on their profile first."]);

    expect(DeviceCommand::count())->toBe(0);
});

test('an employee who resigns is deleted from the devices that hold them', function () {
    $employee = Employee::factory()->create(['device_pin' => '7']);
    DeviceEnrollment::factory()->for($employee)->create();

    $employee->update(['status' => EmploymentStatus::Resigned, 'exit_date' => '2026-10-31']);

    expect(DeviceCommand::sole()->command)->toBe('DATA DELETE USERINFO PIN=7')
        ->and(DeviceEnrollment::sole()->status)->toBe(EnrollmentStatus::Removing);
});

test('a new device can receive every active employee of its branch', function () {
    $device = Device::factory()->create();
    Employee::factory()->count(2)->create(['branch_id' => $device->branch_id]);
    Employee::factory()->create();

    $this->actingAs(userWithRole(RoleEnum::DeviceAdmin))
        ->post(route('devices.action', $device), ['action' => 'push-all-employees'])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Queued 2 employees with their stored biometrics.']);
});
