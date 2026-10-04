<?php

namespace App\Http\Requests\Payroll;

use App\Enums\PermissionEnum;
use App\Models\PayrollRun;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(PermissionEnum::PayrollRun->value);
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $branchRule = Rule::exists('branches', 'id')->whereNull('deleted_at');

        return [
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'branch_id' => [$this->user()->branch_id ? 'required' : 'nullable', 'integer', $this->user()->branch_id ? $branchRule->where('id', $this->user()->branch_id) : $branchRule],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * The overlap is also enforced in PayrollService (race-safe); this shows it on the field.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $start = CarbonImmutable::parse($this->input('period_start'));
                $end = CarbonImmutable::parse($this->input('period_end'));

                if ($start->diffInDays($end) > 62) {
                    $validator->errors()->add('period_end', 'A payroll period can be at most two months.');
                }

                $overlapping = PayrollRun::query()->overlapping($start, $end, $this->input('branch_id') ? (int) $this->input('branch_id') : null)->first();

                if ($overlapping) {
                    $validator->errors()->add('period_start', "Run {$overlapping->reference} already covers part of this period.");
                }
            },
        ];
    }
}
