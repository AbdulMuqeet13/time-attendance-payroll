<?php

namespace App\Http\Requests\Devices;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rename, claim to a branch, enable/disable, and set automatic backups.
 */
class UpdateDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('device'));
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $branchRule = Rule::exists('branches', 'id')->whereNull('deleted_at');

        if ($this->user()->branch_id) {
            $branchRule->where('id', $this->user()->branch_id);
        }

        return [
            'name' => ['required', 'string', 'max:100'],
            'branch_id' => ['nullable', 'integer', $branchRule],
            'is_active' => ['boolean'],
            'auto_backup' => ['required', Rule::in(['none', 'daily', 'weekly'])],
            'backup_retention' => ['required', 'integer', 'min:1', 'max:60'],
        ];
    }
}
