<?php

namespace App\Support;

use App\Enums\DayBasis;
use App\Enums\LatePolicy;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Company-wide settings with typed accessors. Values are stored in the `settings` table
 * and cached; anything not stored falls back to DEFAULTS.
 */
class Settings
{
    private const CACHE_KEY = 'app-settings';

    /**
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        'company.name' => 'My Company',
        'company.address' => '',
        'company.phone' => '',

        'attendance.late_grace_minutes' => 15,
        'attendance.early_window_minutes' => 60,
        'attendance.checkout_grace_minutes' => 120,
        'attendance.duplicate_scan_minutes' => 2,
        'attendance.half_day_minutes' => 240,
        'attendance.min_overtime_minutes' => 30,
        'attendance.overtime_requires_approval' => true,

        'payroll.day_basis' => 'calendar_days',
        'payroll.standard_hours_per_day' => 8,
        'payroll.late_policy' => 'count_based',
        'payroll.lates_per_deduction' => 3,
        'payroll.late_deduction_days' => 1,
        'payroll.deduct_short_hours' => false,
        'payroll.overtime_rate' => 1.5,
        'payroll.overtime_holiday_rate' => 2.0,
    ];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $loaded = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->all();

        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    /**
     * All settings, stored values merged over the defaults.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->loaded ??= array_merge(
            self::DEFAULTS,
            Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all()),
        );
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
        $this->loaded = null;
    }

    public function companyName(): string
    {
        return (string) $this->get('company.name');
    }

    public function lateGraceMinutes(): int
    {
        return (int) $this->get('attendance.late_grace_minutes');
    }

    public function earlyWindowMinutes(): int
    {
        return (int) $this->get('attendance.early_window_minutes');
    }

    public function checkoutGraceMinutes(): int
    {
        return (int) $this->get('attendance.checkout_grace_minutes');
    }

    public function duplicateScanMinutes(): int
    {
        return (int) $this->get('attendance.duplicate_scan_minutes');
    }

    public function halfDayMinutes(): int
    {
        return (int) $this->get('attendance.half_day_minutes');
    }

    public function minOvertimeMinutes(): int
    {
        return (int) $this->get('attendance.min_overtime_minutes');
    }

    public function overtimeRequiresApproval(): bool
    {
        return (bool) $this->get('attendance.overtime_requires_approval');
    }

    public function dayBasis(): DayBasis
    {
        return DayBasis::tryFrom((string) $this->get('payroll.day_basis')) ?? DayBasis::CalendarDays;
    }

    public function standardHoursPerDay(): float
    {
        return (float) $this->get('payroll.standard_hours_per_day');
    }

    public function latePolicy(): LatePolicy
    {
        return LatePolicy::tryFrom((string) $this->get('payroll.late_policy')) ?? LatePolicy::None;
    }

    public function latesPerDeduction(): int
    {
        return max(1, (int) $this->get('payroll.lates_per_deduction'));
    }

    public function lateDeductionDays(): float
    {
        return (float) $this->get('payroll.late_deduction_days');
    }

    public function deductShortHours(): bool
    {
        return (bool) $this->get('payroll.deduct_short_hours');
    }

    public function overtimeRate(): float
    {
        return (float) $this->get('payroll.overtime_rate');
    }

    public function overtimeHolidayRate(): float
    {
        return (float) $this->get('payroll.overtime_holiday_rate');
    }
}
