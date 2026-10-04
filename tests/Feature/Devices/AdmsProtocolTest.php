<?php

use App\Enums\DeviceCommandStatus;
use App\Enums\PunchSource;
use App\Events\DeviceCommandFinished;
use App\Events\PunchesRecorded;
use App\Models\AttendancePunch;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\DeviceUser;
use App\Models\Employee;
use App\Services\Adms\AdmsCommandBuilder;
use App\Services\Adms\DeviceCommandQueue;
use Illuminate\Support\Facades\Event;

test('an unknown device is registered as unclaimed on its handshake', function () {
    $this->get('/iclock/cdata?SN=CQZ7230001&options=all&pushver=2.4.1')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSeeText('GET OPTION FROM: CQZ7230001')
        ->assertSeeText('ATTLOGStamp=0')
        ->assertSeeText('TimeZone=5');

    expect(Device::sole())
        ->serial_number->toBe('CQZ7230001')
        ->branch_id->toBeNull()
        ->push_version->toBe('2.4.1');
});

test('the handshake returns the stamps already received so the device skips old logs', function () {
    $device = Device::factory()->create(['last_attlog_stamp' => '812345678', 'last_operlog_stamp' => '812300000']);

    $this->get("/iclock/cdata?SN={$device->serial_number}&options=all")
        ->assertSeeText('ATTLOGStamp=812345678')
        ->assertSeeText('OPERLOGStamp=812300000');
});

test('attendance scans are stored once and matched to employees by PIN', function () {
    Event::fake([PunchesRecorded::class]);
    $device = Device::factory()->create();
    $employee = Employee::factory()->create(['device_pin' => '7']);
    $body = "7\t2026-10-05 09:02:11\t0\t1\t0\t0\n99\t2026-10-05 09:05:00\t0\t15\n";

    devicePost("/iclock/cdata?SN={$device->serial_number}&table=ATTLOG&Stamp=812345700", $body)->assertSeeText('OK: 2');
    devicePost("/iclock/cdata?SN={$device->serial_number}&table=ATTLOG&Stamp=812345700", $body)->assertSeeText('OK: 0');

    expect(AttendancePunch::count())->toBe(2)
        ->and(AttendancePunch::where('pin', '7')->sole())
        ->employee_id->toBe($employee->id)
        ->verify_type->toBe(1)
        ->source->toBe(PunchSource::Device)
        ->and(AttendancePunch::where('pin', '99')->sole()->employee_id)->toBeNull()
        ->and($device->fresh()->last_attlog_stamp)->toBe('812345700');

    Event::assertDispatchedTimes(PunchesRecorded::class, 1);
    Event::assertDispatched(PunchesRecorded::class, fn (PunchesRecorded $event) => $event->employeeDates === [$employee->id => ['2026-10-05']]);
});

test('scans from an unclaimed device are ignored', function () {
    $device = Device::factory()->unclaimed()->create();

    devicePost("/iclock/cdata?SN={$device->serial_number}&table=ATTLOG", "7\t2026-10-05 09:02:11\t0\t1\n")->assertSeeText('OK');

    expect(AttendancePunch::count())->toBe(0);
});

test('scans with an unknown PIN are linked once an employee gets that PIN', function () {
    $device = Device::factory()->create();
    devicePost("/iclock/cdata?SN={$device->serial_number}&table=ATTLOG", "42\t2026-10-05 09:00:00\t0\t1\n");

    $employee = Employee::factory()->create(['device_pin' => '42']);

    expect(AttendancePunch::sole()->employee_id)->toBe($employee->id);
});

test('users reported in the operation log are stored as device users', function () {
    $device = Device::factory()->create();
    $body = "USER PIN=7\tName=Ali Raza\tPri=0\tPasswd=\tCard=12345\tGrp=1\nFP PIN=7\tFID=6\tSize=1184\tValid=1\tTMP=ocosgdGU...\nOPLOG 4\t0\t2026-10-05 09:00:00\t0\t0\t0\t0\n";

    devicePost("/iclock/cdata?SN={$device->serial_number}&table=OPERLOG&OpStamp=900", $body)->assertSeeText('OK: 2');

    expect(DeviceUser::sole())
        ->pin->toBe('7')
        ->name->toBe('Ali Raza')
        ->card->toBe('12345')
        ->and($device->fresh()->last_operlog_stamp)->toBe('900');
});

test('polling delivers queued commands in order and marks them sent', function () {
    $device = Device::factory()->create();
    $builder = app(AdmsCommandBuilder::class);
    app(DeviceCommandQueue::class)->queueMany($device, [$builder->updateUser('7', 'Ali Raza'), $builder->reboot()]);

    $this->get("/iclock/getrequest?SN={$device->serial_number}&INFO=Ver 8.0.4,15,30,1200,192.168.1.201,10,7,12,4")
        ->assertOk()
        ->assertSee("C:1:DATA UPDATE USERINFO PIN=7\tName=Ali Raza\tPri=0\tPasswd=\tCard=\nC:2:REBOOT", false);

    expect(DeviceCommand::pluck('status')->all())->toBe([DeviceCommandStatus::Sent, DeviceCommandStatus::Sent])
        ->and($device->fresh())
        ->user_count->toBe(15)
        ->fp_count->toBe(30)
        ->ip_address->toBe('192.168.1.201')
        ->isOnline()->toBeTrue();

    $this->get("/iclock/getrequest?SN={$device->serial_number}")->assertSeeText('OK');
});

test('command results mark success and failure, and a failure does not block later commands', function () {
    Event::fake([DeviceCommandFinished::class]);
    $device = Device::factory()->create();
    $first = DeviceCommand::factory()->for($device)->sent()->create(['sequence' => 1]);
    $second = DeviceCommand::factory()->for($device)->sent()->create(['sequence' => 2]);
    $third = DeviceCommand::factory()->for($device)->create(['sequence' => 3]);

    devicePost("/iclock/devicecmd?SN={$device->serial_number}", "ID=1&Return=-1002&CMD=DATA\nID=2&Return=0&CMD=REBOOT\n")->assertSeeText('OK');

    expect($first->fresh())->status->toBe(DeviceCommandStatus::Failed)->return_code->toBe(-1002)
        ->and($second->fresh()->status)->toBe(DeviceCommandStatus::Succeeded);

    Event::assertDispatchedTimes(DeviceCommandFinished::class, 2);

    $this->get("/iclock/getrequest?SN={$device->serial_number}")->assertSeeText('C:3:INFO');
    expect($third->fresh()->status)->toBe(DeviceCommandStatus::Sent);
});

test('an unanswered command is re-sent, then given up after three attempts', function () {
    $device = Device::factory()->create();
    $retried = DeviceCommand::factory()->for($device)->sent(now()->subMinutes(10))->create(['sequence' => 1, 'attempts' => 1]);
    $abandoned = DeviceCommand::factory()->for($device)->sent(now()->subMinutes(10))->create(['sequence' => 2, 'attempts' => 3]);

    $this->get("/iclock/getrequest?SN={$device->serial_number}")->assertSeeText('C:1:INFO');

    expect($retried->fresh())->status->toBe(DeviceCommandStatus::Sent)->attempts->toBe(2)
        ->and($abandoned->fresh()->status)->toBe(DeviceCommandStatus::Failed);
});

test('PUSH 3 query results are answered with the table and record count', function () {
    $device = Device::factory()->create(['push_version' => '3.1.2']);

    devicePost(
        "/iclock/querydata?SN={$device->serial_number}&type=tabledata&tablename=user&count=2",
        "user uid=1\tcardno=\tpin=7\tpassword=\tgroup=1\tname=Ali\tprivilege=0\nuser uid=2\tcardno=\tpin=8\tpassword=123\tgroup=1\tname=Sara\tprivilege=14\n",
    )->assertSeeText('user=2');

    expect(DeviceUser::where('pin', '8')->sole())->privilege->toBe(14)->has_password->toBeTrue();
});

test('the same endpoints answer under /api/iclock', function () {
    $this->get('/api/iclock/cdata?SN=APIDEVICE1&options=all')->assertSeeText('GET OPTION FROM: APIDEVICE1');
    $this->get('/iclock/unknown/path?SN=APIDEVICE1')->assertSeeText('OK');
});
