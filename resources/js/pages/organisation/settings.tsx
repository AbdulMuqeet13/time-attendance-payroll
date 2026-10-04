import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import Heading from '@/components/heading';
import type { SelectOption } from '@/components/option-select';
import { OptionSelect } from '@/components/option-select';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import {
    edit,
    update,
} from '@/actions/App/Http/Controllers/Organisation/CompanySettingsController';
import { dashboard } from '@/routes';

type SettingValue = string | number | boolean;

type SettingsPageProps = {
    settings: Record<string, SettingValue>;
    dayBases: SelectOption[];
    latePolicies: SelectOption[];
};

type NumberField = { key: string; label: string; hint: string; step?: string };

const ATTENDANCE_FIELDS: NumberField[] = [
    {
        key: 'attendance__late_grace_minutes',
        label: 'Late after (minutes)',
        hint: 'A check-in later than shift start + this is late.',
    },
    {
        key: 'attendance__early_window_minutes',
        label: 'Early check-in window (minutes)',
        hint: 'Scans this long before a shift starts count for that shift.',
    },
    {
        key: 'attendance__checkout_grace_minutes',
        label: 'Check-out grace (minutes)',
        hint: 'Scans this long after a shift ends still count as its check-out.',
    },
    {
        key: 'attendance__duplicate_scan_minutes',
        label: 'Ignore repeat scans (minutes)',
        hint: 'Scans this close together count as one.',
    },
    {
        key: 'attendance__half_day_minutes',
        label: 'Half day below (minutes worked)',
        hint: 'Working less than this in a shift makes it a half day.',
    },
    {
        key: 'attendance__min_overtime_minutes',
        label: 'Minimum overtime (minutes)',
        hint: 'Extra time below this is not counted as overtime.',
    },
];

export default function CompanySettings({
    settings,
    dayBases,
    latePolicies,
}: SettingsPageProps) {
    const { data, setData, put, processing, errors } =
        useForm<Record<string, SettingValue>>(settings);

    const errorFor = (key: string) =>
        (errors as Record<string, string | undefined>)[key];

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        put(update().url, { preserveScroll: true });
    }

    const numberInput = (field: NumberField) => (
        <FormField
            key={field.key}
            label={field.label}
            htmlFor={field.key}
            error={errorFor(field.key)}
            hint={field.hint}
        >
            <Input
                id={field.key}
                type="number"
                min="0"
                step={field.step ?? '1'}
                value={String(data[field.key] ?? '')}
                onChange={(e) => setData(field.key, e.target.value)}
            />
        </FormField>
    );

    const toggle = (key: string, label: string, hint: string) => (
        <div className="flex items-start justify-between gap-4 rounded-lg border p-3">
            <div>
                <p className="text-sm font-medium">{label}</p>
                <p className="text-xs text-muted-foreground">{hint}</p>
            </div>
            <Switch
                checked={Boolean(data[key])}
                onCheckedChange={(checked) => setData(key, checked)}
            />
        </div>
    );

    return (
        <>
            <Head title="Company Settings" />

            <form
                onSubmit={handleSubmit}
                className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4"
            >
                <Heading
                    title="Company Settings"
                    description="Company details and the rules used to process attendance and payroll."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Company</CardTitle>
                        <CardDescription>
                            Shown on payslips and reports.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-3">
                        <FormField
                            label="Company name"
                            htmlFor="company__name"
                            error={errorFor('company__name')}
                            required
                        >
                            <Input
                                id="company__name"
                                value={String(data.company__name ?? '')}
                                onChange={(e) =>
                                    setData('company__name', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Address"
                            htmlFor="company__address"
                            error={errorFor('company__address')}
                        >
                            <Input
                                id="company__address"
                                value={String(data.company__address ?? '')}
                                onChange={(e) =>
                                    setData('company__address', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Phone"
                            htmlFor="company__phone"
                            error={errorFor('company__phone')}
                        >
                            <Input
                                id="company__phone"
                                value={String(data.company__phone ?? '')}
                                onChange={(e) =>
                                    setData('company__phone', e.target.value)
                                }
                            />
                        </FormField>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Attendance rules</CardTitle>
                        <CardDescription>
                            Company defaults. Each shift can override the timing
                            rules.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {ATTENDANCE_FIELDS.map(numberInput)}
                        </div>
                        {toggle(
                            'attendance__overtime_requires_approval',
                            'Overtime needs approval',
                            'When on, overtime is only paid after a manager approves it.',
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Payroll rules</CardTitle>
                        <CardDescription>
                            How the monthly salary is adjusted for attendance.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <FormField
                                label="Per-day rate = salary ÷"
                                htmlFor="payroll__day_basis"
                                error={errorFor('payroll__day_basis')}
                                hint="Used for absences, unpaid leave and half days."
                            >
                                <OptionSelect
                                    id="payroll__day_basis"
                                    value={String(data.payroll__day_basis)}
                                    onChange={(value) =>
                                        setData('payroll__day_basis', value)
                                    }
                                    options={dayBases}
                                />
                            </FormField>
                            {numberInput({
                                key: 'payroll__standard_hours_per_day',
                                label: 'Standard hours per day',
                                hint: 'Hourly rate = per-day rate ÷ these hours.',
                                step: '0.5',
                            })}
                            <FormField
                                label="Late deduction"
                                htmlFor="payroll__late_policy"
                                error={errorFor('payroll__late_policy')}
                                hint="How lates reduce pay."
                            >
                                <OptionSelect
                                    id="payroll__late_policy"
                                    value={String(data.payroll__late_policy)}
                                    onChange={(value) =>
                                        setData('payroll__late_policy', value)
                                    }
                                    options={latePolicies}
                                />
                            </FormField>
                            {data.payroll__late_policy === 'count_based' && (
                                <>
                                    {numberInput({
                                        key: 'payroll__lates_per_deduction',
                                        label: 'Every N lates…',
                                        hint: 'Number of lates in a month that trigger a deduction.',
                                    })}
                                    {numberInput({
                                        key: 'payroll__late_deduction_days',
                                        label: '…deduct days',
                                        hint: 'Days of pay deducted each time.',
                                        step: '0.5',
                                    })}
                                </>
                            )}
                            {numberInput({
                                key: 'payroll__overtime_rate',
                                label: 'Overtime rate (× hourly)',
                                hint: 'e.g. 1.5 pays time and a half.',
                                step: '0.25',
                            })}
                            {numberInput({
                                key: 'payroll__overtime_holiday_rate',
                                label: 'Holiday / off-day overtime rate',
                                hint: 'Applied to work on holidays and weekly offs.',
                                step: '0.25',
                            })}
                        </div>
                        {toggle(
                            'payroll__deduct_short_hours',
                            'Deduct early leaving',
                            'Deduct leaving before shift end at the hourly rate.',
                        )}
                    </CardContent>
                </Card>

                <div className="flex justify-end">
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving...' : 'Save Settings'}
                    </Button>
                </div>
            </form>
        </>
    );
}

CompanySettings.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Company Settings', href: edit().url },
    ],
};
