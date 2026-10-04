<?php

namespace Database\Seeders;

use App\Enums\AdjustmentKind;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\LeaveStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleEnum;
use App\Enums\SalaryChangeType;
use App\Models\AttendancePunch;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollAdjustment;
use App\Models\SalaryAdvance;
use App\Models\SalaryComponent;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Attendance\AttendanceProcessor;
use App\Services\SalaryService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Sample company for trying the app: php artisan db:seed --class=DemoDataSeeder
 *
 * Creates two branches, shifts, twelve employees with salaries and device PINs, a claimed device, last month's
 * and this month's scans (with lates, absences, overtime and a forgotten check-out), a holiday, leave,
 * a bonus and an advance, then calculates attendance. Logins (password "password"): hr@example.com,
 * payroll@example.com, manager@example.com (Lahore branch only), and ali@example.com (employee portal).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
        mt_srand(42);

        $lahore = Branch::query()->firstOrCreate(['code' => 'LHR'], ['name' => 'Lahore Head Office', 'address' => 'Gulberg III, Lahore', 'phone' => '042-35710000']);
        $karachi = Branch::query()->firstOrCreate(['code' => 'KHI'], ['name' => 'Karachi Plant', 'address' => 'Korangi Industrial Area, Karachi']);

        $departments = collect(['Production', 'Accounts', 'Human Resources', 'Sales'])
            ->mapWithKeys(fn (string $name) => [$name => Department::query()->firstOrCreate(['name' => $name])]);
        $designations = collect(['Machine Operator', 'Supervisor', 'Accountant', 'HR Officer', 'Sales Executive'])
            ->mapWithKeys(fn (string $name) => [$name => Designation::query()->firstOrCreate(['name' => $name])]);

        $day = Shift::query()->firstOrCreate(['name' => 'Day'], ['start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 60, 'color' => 'sky']);
        $evening = Shift::query()->firstOrCreate(['name' => 'Evening'], ['start_time' => '14:00', 'end_time' => '22:00', 'break_minutes' => 30, 'color' => 'amber']);
        $night = Shift::query()->firstOrCreate(['name' => 'Night'], ['start_time' => '22:00', 'end_time' => '06:00', 'break_minutes' => 30, 'color' => 'violet']);

        $from = CarbonImmutable::today()->subMonthNoOverflow()->startOfMonth();
        $device = Device::query()->firstOrCreate(['serial_number' => 'DEMO0000001'], [
            'name' => 'Lahore main gate', 'branch_id' => $lahore->id, 'push_version' => '2.4.1', 'firmware' => 'Ver 8.0.4.2',
            'fp_algorithm' => '10', 'face_algorithm' => '7', 'last_seen_at' => now(), 'ip_address' => '192.168.1.201',
        ]);

        $people = [
            ['Ali Raza', Gender::Male, 'Production', 'Machine Operator', 65000, $day, $lahore],
            ['Sara Ahmed', Gender::Female, 'Accounts', 'Accountant', 90000, $day, $lahore],
            ['Bilal Khan', Gender::Male, 'Production', 'Machine Operator', 60000, $evening, $lahore],
            ['Ayesha Malik', Gender::Female, 'Human Resources', 'HR Officer', 85000, $day, $lahore],
            ['Usman Tariq', Gender::Male, 'Production', 'Supervisor', 110000, $night, $lahore],
            ['Hina Iqbal', Gender::Female, 'Sales', 'Sales Executive', 70000, $day, $lahore],
            ['Hamza Sheikh', Gender::Male, 'Production', 'Machine Operator', 58000, $evening, $karachi],
            ['Zainab Hussain', Gender::Female, 'Accounts', 'Accountant', 88000, $day, $karachi],
            ['Faisal Mehmood', Gender::Male, 'Production', 'Supervisor', 105000, $night, $karachi],
            ['Maryam Javed', Gender::Female, 'Sales', 'Sales Executive', 72000, $day, $karachi],
            ['Kamran Akhtar', Gender::Male, 'Production', 'Machine Operator', 61000, $day, $karachi],
            ['Nadia Rehman', Gender::Female, 'Human Resources', 'HR Officer', 80000, $day, $karachi],
        ];

        $components = SalaryComponent::query()->pluck('id', 'name');
        $employees = collect();

        foreach ($people as $index => [$name, $gender, $department, $designation, $gross, $shift, $branch]) {
            $employee = Employee::query()->firstOrCreate(['employee_code' => sprintf('EMP-%04d', $index + 1)], [
                'name' => $name,
                'gender' => $gender,
                'cnic' => sprintf('35202-%07d-%d', 1000000 + $index, $index % 9 + 1),
                'phone' => '0300'.str_pad((string) (1000000 + $index), 7, '0', STR_PAD_LEFT),
                'email' => Str::slug(explode(' ', $name)[0]).'@example.com',
                'branch_id' => $branch->id,
                'department_id' => $departments[$department]->id,
                'designation_id' => $designations[$designation]->id,
                'employment_type' => EmploymentType::Permanent,
                'joining_date' => $index === 11 ? $from->addDays(14)->toDateString() : '2024-03-01',
                'payment_method' => $index % 5 === 4 ? PaymentMethod::Cash : PaymentMethod::Bank,
                'bank_name' => $index % 5 === 4 ? null : 'Meezan Bank',
                'account_title' => $name,
                'account_number' => $index % 5 === 4 ? null : 'PK36MEZN00'.str_pad((string) (2000000 + $index), 14, '0', STR_PAD_LEFT),
                'device_pin' => (string) ($index + 1),
            ]);

            if ($employee->salaries()->doesntExist()) {
                app(SalaryService::class)->record($employee, [
                    'effective_date' => $employee->joining_date->toDateString(),
                    'change_type' => SalaryChangeType::Initial->value,
                    'components' => [
                        ['salary_component_id' => $components['Basic Salary'], 'amount' => (string) round($gross * 0.6)],
                        ['salary_component_id' => $components['House Rent'], 'amount' => (string) round($gross * 0.25)],
                        ['salary_component_id' => $components['Medical'], 'amount' => (string) round($gross * 0.1)],
                        ['salary_component_id' => $components['Conveyance'], 'amount' => (string) ($gross - round($gross * 0.6) - round($gross * 0.25) - round($gross * 0.1))],
                        ['salary_component_id' => $components['Income Tax'], 'amount' => $gross > 80000 ? (string) round($gross * 0.02) : null],
                    ],
                ], null);
            }

            ShiftAssignment::query()->firstOrCreate(
                ['employee_id' => $employee->id, 'shift_id' => $shift->id],
                ['days' => [1, 2, 3, 4, 5, 6], 'effective_from' => '2024-03-01'],
            );

            $employees->push([$employee, $shift]);
        }

        Holiday::query()->firstOrCreate(['date' => $from->addDays(10)->toDateString(), 'branch_id' => null], ['name' => 'Company foundation day']);

        $this->seedScans($employees, $device, $from, CarbonImmutable::today()->subDay());

        [$ali] = $employees[0];
        LeaveRequest::query()->firstOrCreate(['employee_id' => $employees[1][0]->id, 'start_date' => $from->addDays(15)->toDateString()], [
            'leave_type_id' => LeaveType::query()->where('code', 'AL')->value('id'),
            'end_date' => $from->addDays(16)->toDateString(), 'days' => 2, 'reason' => 'Family wedding', 'status' => LeaveStatus::Approved, 'decided_at' => now(),
        ]);
        LeaveRequest::query()->firstOrCreate(['employee_id' => $ali->id, 'start_date' => CarbonImmutable::today()->addDays(7)->toDateString()], [
            'leave_type_id' => LeaveType::query()->where('code', 'CL')->value('id'),
            'end_date' => CarbonImmutable::today()->addDays(7)->toDateString(), 'days' => 1, 'reason' => 'Doctor appointment', 'status' => LeaveStatus::Pending,
        ]);
        PayrollAdjustment::query()->firstOrCreate(['employee_id' => $employees[4][0]->id, 'period' => $from->toDateString(), 'name' => 'Production target bonus'], [
            'kind' => AdjustmentKind::Bonus, 'amount' => 10000,
        ]);
        SalaryAdvance::query()->firstOrCreate(['employee_id' => $employees[2][0]->id], [
            'amount' => 30000, 'issued_on' => $from->subDays(5)->toDateString(), 'installment_amount' => 5000, 'start_period' => $from->toDateString(),
        ]);

        $this->seedUsers($lahore, $ali);

        $processor = app(AttendanceProcessor::class);
        $employees->each(fn (array $pair) => $processor->rebuild($pair[0], $from, CarbonImmutable::today()));
    }

    /**
     * Scans for every working day: mostly on time, some late, some absent, some overtime, one forgotten check-out.
     *
     * @param  Collection<int, array{0: Employee, 1: Shift}>  $employees
     */
    private function seedScans(Collection $employees, Device $device, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $rows = [];

        foreach ($employees as $position => [$employee, $shift]) {
            foreach (CarbonPeriod::create($from, $to) as $date) {
                $date = CarbonImmutable::parse($date);

                if ($date->isSunday() || $date->lt($employee->joining_date) || mt_rand(1, 100) <= 4) {
                    continue;
                }

                $in = $shift->startsAt($date)->addMinutes(mt_rand(1, 100) <= 15 ? mt_rand(16, 50) : mt_rand(-12, 8));
                $out = $shift->endsAt($date)->addMinutes(mt_rand(1, 100) <= 12 ? mt_rand(40, 150) : mt_rand(0, 10));

                foreach ([$in, $out] as $scan) {
                    if ($scan->gt(now())) {
                        continue;
                    }

                    if ($scan === $out && $position === 0 && $date->equalTo($from->addDays(3))) {
                        continue;
                    }

                    $at = $scan->format('Y-m-d H:i:s');
                    $rows[] = [
                        'device_id' => $device->id, 'employee_id' => $employee->id, 'pin' => $employee->device_pin, 'punched_at' => $at,
                        'punch_state' => $scan === $in ? 0 : 1, 'verify_type' => 1, 'source' => 'device',
                        'dedupe_hash' => AttendancePunch::hashFor($device->serial_number, $employee->device_pin, $at),
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            AttendancePunch::query()->insertOrIgnore($chunk);
        }
    }

    private function seedUsers(Branch $lahore, Employee $ali): void
    {
        $accounts = [
            ['hr@example.com', 'Hira (HR)', RoleEnum::HrManager, null],
            ['payroll@example.com', 'Imran (Payroll)', RoleEnum::PayrollOfficer, null],
            ['manager@example.com', 'Tahir (Lahore manager)', RoleEnum::BranchManager, $lahore->id],
            ['devices@example.com', 'Device admin', RoleEnum::DeviceAdmin, null],
            ['ali@example.com', $ali->name, RoleEnum::Employee, null],
        ];

        foreach ($accounts as [$email, $name, $role, $branchId]) {
            $user = User::query()->firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'password', 'branch_id' => $branchId, 'email_verified_at' => now()]);
            $user->syncRoles([$role->value]);
        }

        $ali->update(['user_id' => User::query()->where('email', 'ali@example.com')->value('id')]);
    }
}
