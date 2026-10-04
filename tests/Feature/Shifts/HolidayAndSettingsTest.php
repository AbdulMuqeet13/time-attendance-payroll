<?php

use App\Enums\RoleEnum;
use App\Models\Holiday;
use App\Support\Settings;

test('two company-wide holidays cannot share a date', function () {
    Holiday::factory()->create(['date' => '2026-03-23', 'branch_id' => null]);

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('holidays.store'), ['date' => '2026-03-23', 'name' => 'Pakistan Day'])
        ->assertSessionHasErrors(['date' => 'There is already a holiday on this date.']);
});

test('an admin saves the attendance and payroll policies', function () {
    $payload = collect(Settings::DEFAULTS)
        ->mapWithKeys(fn ($value, $key) => [str_replace('.', '__', $key) => $value])
        ->merge(['payroll__late_policy' => 'per_minute', 'attendance__late_grace_minutes' => 10, 'company__name' => 'Acme Textiles'])
        ->all();

    $this->actingAs(userWithRole(RoleEnum::SuperAdmin))
        ->put(route('company-settings.update'), $payload)
        ->assertSessionHasNoErrors();

    $settings = app(Settings::class);

    expect($settings->lateGraceMinutes())->toBe(10)
        ->and($settings->latePolicy()->value)->toBe('per_minute')
        ->and($settings->companyName())->toBe('Acme Textiles');
});

test('HR managers cannot change company settings', function () {
    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->get(route('company-settings.edit'))
        ->assertForbidden();
});
