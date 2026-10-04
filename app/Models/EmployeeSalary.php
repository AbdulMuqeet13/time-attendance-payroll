<?php

namespace App\Models;

use App\Enums\SalaryChangeType;
use App\Models\Concerns\SerializesLocalDates;
use Database\Factories\EmployeeSalaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One salary record in an employee's history. Nothing is overwritten: increments and revisions add new records.
 *
 * @property int $id
 * @property int $employee_id
 * @property Carbon $effective_date
 * @property SalaryChangeType $change_type
 * @property string $gross_salary
 * @property string $fixed_deductions
 * @property string|null $remarks
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read User|null $creator
 * @property-read Collection<int, EmployeeSalaryComponent> $components
 */
#[Fillable(['employee_id', 'effective_date', 'change_type', 'gross_salary', 'fixed_deductions', 'remarks', 'created_by'])]
class EmployeeSalary extends Model
{
    /** @use HasFactory<EmployeeSalaryFactory> */
    use HasFactory, LogsActivity, SerializesLocalDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date:Y-m-d',
            'change_type' => SalaryChangeType::class,
            'gross_salary' => 'decimal:2',
            'fixed_deductions' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<EmployeeSalaryComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }

    /**
     * @return HasMany<Payslip, $this>
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
