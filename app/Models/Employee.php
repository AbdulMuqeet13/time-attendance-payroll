<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\PaymentMethod;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\EmployeeObserver;
use Carbon\CarbonInterface;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $employee_code
 * @property string $name
 * @property string|null $father_name
 * @property string|null $cnic
 * @property Gender $gender
 * @property Carbon|null $date_of_birth
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $photo_path
 * @property int $branch_id
 * @property int|null $department_id
 * @property int|null $designation_id
 * @property int|null $reports_to_id
 * @property EmploymentType $employment_type
 * @property EmploymentStatus $status
 * @property Carbon $joining_date
 * @property Carbon|null $confirmation_date
 * @property Carbon|null $exit_date
 * @property PaymentMethod $payment_method
 * @property string|null $bank_name
 * @property string|null $account_title
 * @property string|null $account_number
 * @property string|null $device_pin
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Branch $branch
 * @property-read Department|null $department
 * @property-read Designation|null $designation
 * @property-read Employee|null $manager
 * @property-read User|null $user
 * @property-read Collection<int, EmployeeSalary> $salaries
 * @property-read EmployeeSalary|null $currentSalary
 */
#[Fillable([
    'employee_code', 'name', 'father_name', 'cnic', 'gender', 'date_of_birth', 'phone', 'email', 'address', 'photo_path',
    'branch_id', 'department_id', 'designation_id', 'reports_to_id',
    'employment_type', 'status', 'joining_date', 'confirmation_date', 'exit_date',
    'payment_method', 'bank_name', 'account_title', 'account_number', 'device_pin', 'user_id',
])]
#[ObservedBy(EmployeeObserver::class)]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, LogsActivity, SerializesLocalDates, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'employment_type' => EmploymentType::class,
            'status' => EmploymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'date_of_birth' => 'date:Y-m-d',
            'joining_date' => 'date:Y-m-d',
            'confirmation_date' => 'date:Y-m-d',
            'exit_date' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Designation, $this>
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reports_to_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<BiometricTemplate, $this>
     */
    public function biometricTemplates(): HasMany
    {
        return $this->hasMany(BiometricTemplate::class);
    }

    /**
     * @return HasMany<DeviceEnrollment, $this>
     */
    public function deviceEnrollments(): HasMany
    {
        return $this->hasMany(DeviceEnrollment::class);
    }

    /**
     * @return HasMany<AttendanceDay, $this>
     */
    public function attendanceDays(): HasMany
    {
        return $this->hasMany(AttendanceDay::class);
    }

    /**
     * @return HasMany<AttendancePunch, $this>
     */
    public function punches(): HasMany
    {
        return $this->hasMany(AttendancePunch::class);
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * @return HasMany<Payslip, $this>
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /**
     * @return HasMany<PayrollAdjustment, $this>
     */
    public function payrollAdjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }

    /**
     * @return HasMany<SalaryAdvance, $this>
     */
    public function salaryAdvances(): HasMany
    {
        return $this->hasMany(SalaryAdvance::class);
    }

    /**
     * @return HasMany<ShiftAssignment, $this>
     */
    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    /**
     * @return HasMany<RosterOverride, $this>
     */
    public function rosterOverrides(): HasMany
    {
        return $this->hasMany(RosterOverride::class);
    }

    /**
     * Salary history, newest first.
     *
     * @return HasMany<EmployeeSalary, $this>
     */
    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class)
            ->orderByDesc('effective_date')
            ->orderByDesc('id');
    }

    /**
     * The salary record in effect today (future-dated increments excluded).
     *
     * @return HasOne<EmployeeSalary, $this>
     */
    public function currentSalary(): HasOne
    {
        return $this->hasOne(EmployeeSalary::class)->ofMany(
            ['effective_date' => 'max', 'id' => 'max'],
            fn (Builder $query) => $query->where('effective_date', '<=', now()->toDateString()),
        );
    }

    /**
     * The latest salary record effective on or before the given date.
     */
    public function salaryEffectiveOn(CarbonInterface|string $date): ?EmployeeSalary
    {
        $date = $date instanceof CarbonInterface ? $date->toDateString() : $date;

        return $this->hasMany(EmployeeSalary::class)
            ->where('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Whether the employee was on the payroll on the given day (joined and not yet left).
     */
    public function isEmployedOn(CarbonInterface $date): bool
    {
        return $this->joining_date->lte($date) && ($this->exit_date === null || $this->exit_date->gte($date));
    }

    /**
     * @param  Builder<Employee>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', EmploymentStatus::Active);
    }

    /**
     * Employees on the payroll at any point between the two dates.
     *
     * @param  Builder<Employee>  $query
     */
    public function scopeEmployedBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->where('joining_date', '<=', $to->toDateString())
            ->where(fn (Builder $query) => $query->whereNull('exit_date')->orWhere('exit_date', '>=', $from->toDateString()));
    }

    /**
     * Limit to the employees the user may see: users tied to a branch only see that branch.
     *
     * @param  Builder<Employee>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->when($user->branch_id, fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId));
    }

    /**
     * @param  Builder<Employee>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
            ->where('name', 'like', "%{$term}%")
            ->orWhere('employee_code', 'like', "%{$term}%")
            ->orWhere('cnic', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%")
            ->orWhere('device_pin', $term)));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
