<?php

namespace App\Models;

use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_salary_id
 * @property int $salary_component_id
 * @property string $amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EmployeeSalary $employeeSalary
 * @property-read SalaryComponent $salaryComponent
 */
#[Fillable(['employee_salary_id', 'salary_component_id', 'amount'])]
class EmployeeSalaryComponent extends Model
{
    use SerializesLocalDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<EmployeeSalary, $this>
     */
    public function employeeSalary(): BelongsTo
    {
        return $this->belongsTo(EmployeeSalary::class);
    }

    /**
     * Deleted components still label old salary records.
     *
     * @return BelongsTo<SalaryComponent, $this>
     */
    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class)->withTrashed();
    }
}
