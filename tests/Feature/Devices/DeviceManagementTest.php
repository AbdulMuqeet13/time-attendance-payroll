<?php

use App\Enums\DeviceCommandType;
use App\Enums\RoleEnum;
use App\Models\AttendancePunch;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\Employee;

test('a device admin claims an unclaimed device for a branch', function () {
    $device = Device::factory()->unclaimed()->create();
    $branch = Branch::factory()->create();

    $this->actingAs(userWithRole(RoleEnum::DeviceAdmin))
        ->put(route('devices.update', $device), [
            'name' => 'Head office gate', 'branch_id' => $branch->id, 'is_active' => true, 'auto_backup' => 'daily', 'backup_retention' => 7,
        ])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Device claimed. Its scans are recorded from now on.']);

    expect($device->fresh())->branch_id->toBe($branch->id)->acceptsData()->toBeTrue();
});

test('a branch-scoped user cannot see another branch or unclaimed devices', function (Closure $makeDevice) {
    $user = userWithRole(RoleEnum::DeviceAdmin, ['branch_id' => Branch::factory()->create()->id]);

    $this->actingAs($user)->get(route('devices.show', $makeDevice()))->assertForbidden();
})->with([
    'other branch' => [fn () => Device::factory()->create()],
    'unclaimed' => [fn () => Device::factory()->unclaimed()->create()],
]);

test('pulling logs queues an attendance query for the date range', function () {
    $device = Device::factory()->create();

    $this->actingAs(userWithRole(RoleEnum::DeviceAdmin))
        ->post(route('devices.action', $device), ['action' => 'pull-logs', 'from' => '2026-10-01', 'to' => '2026-10-03'])
        ->assertSessionHasNoErrors();

    expect(DeviceCommand::sole())
        ->type->toBe(DeviceCommandType::QueryAttendance)
        ->command->toBe("DATA QUERY ATTLOG StartTime=2026-10-01 00:00:00\tEndTime=2026-10-03 23:59:59");
});

test('pulling users from older firmware resets the operation log stamp and asks the device to check in', function () {
    $device = Device::factory()->create(['push_version' => '2.4.1', 'last_operlog_stamp' => '5000']);

    $this->actingAs(userWithRole(RoleEnum::DeviceAdmin))->post(route('devices.action', $device), ['action' => 'pull-users']);

    expect($device->fresh()->last_operlog_stamp)->toBe('0')
        ->and(DeviceCommand::pluck('command')->all())->toBe(['CHECK']);
});

test('pulling users from PUSH 3 firmware queries the user and template tables', function () {
    $device = Device::factory()->create(['push_version' => '3.1.2']);

    $this->actingAs(userWithRole(RoleEnum::DeviceAdmin))->post(route('devices.action', $device), ['action' => 'pull-users']);

    expect(DeviceCommand::orderBy('sequence')->pluck('command')->all())->toBe([
        'DATA QUERY tablename=user,fielddesc=*,filter=*',
        'DATA QUERY tablename=templatev10,fielddesc=*,filter=*',
        'DATA QUERY tablename=biodata,fielddesc=*,filter=*',
    ]);
});

test('clearing the device log must be confirmed by typing CLEAR', function () {
    $device = Device::factory()->create();

    $this->actingAs(userWithRole(RoleEnum::DeviceAdmin))
        ->post(route('devices.action', $device), ['action' => 'clear-logs', 'confirmation' => 'yes'])
        ->assertSessionHasErrors(['confirmation' => 'Type CLEAR to confirm.']);

    expect(DeviceCommand::count())->toBe(0);
});

test('commands cannot be sent to an unclaimed device', function () {
    $this->actingAs(userWithRole(RoleEnum::DeviceAdmin))
        ->post(route('devices.action', Device::factory()->unclaimed()->create()), ['action' => 'reboot'])
        ->assertForbidden();
});

test('giving an unmatched PIN to an employee attaches their earlier scans', function () {
    $punch = AttendancePunch::factory()->create(['employee_id' => null, 'pin' => '55']);
    $employee = Employee::factory()->create(['device_pin' => null]);

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('unmatched-punches.assign'), ['pin' => '55', 'employee_id' => $employee->id])
        ->assertSessionHasNoErrors();

    expect($punch->fresh()->employee_id)->toBe($employee->id)
        ->and($employee->fresh()->device_pin)->toBe('55');
});
