<?php

namespace App\Http\Requests\Devices;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceActionRequest extends FormRequest
{
    public const ACTIONS = ['reboot', 'refresh-info', 'pull-users', 'pull-logs', 'clear-logs', 'push-all-employees'];

    public function authorize(): bool
    {
        return $this->user()->can('command', $this->route('device'));
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(self::ACTIONS)],
            'from' => ['required_if:action,pull-logs', 'nullable', 'date'],
            'to' => ['required_if:action,pull-logs', 'nullable', 'date', 'after_or_equal:from'],
            'confirmation' => ['required_if:action,clear-logs', 'nullable', 'in:CLEAR'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmation.in' => 'Type CLEAR to confirm.',
            'confirmation.required_if' => 'Type CLEAR to confirm.',
        ];
    }
}
