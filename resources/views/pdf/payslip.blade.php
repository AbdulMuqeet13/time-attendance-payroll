<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payslip {{ $payslip->employee_name }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #111827; margin: 0; }
        h1 { font-size: 18px; margin: 0; }
        h2 { font-size: 13px; margin: 16px 0 6px; }
        .muted { color: #6b7280; }
        .header { border-bottom: 2px solid #111827; padding-bottom: 8px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 6px; text-align: left; vertical-align: top; }
        .lines th { border-bottom: 1px solid #d1d5db; font-weight: bold; }
        .lines td { border-bottom: 1px solid #f3f4f6; }
        .right { text-align: right; }
        .totals td { font-weight: bold; border-top: 1px solid #111827; }
        .net { margin-top: 14px; padding: 10px; background: #f3f4f6; font-size: 14px; font-weight: bold; }
        .grid td { width: 25%; }
    </style>
</head>
<body>
<div class="header">
    <table>
        <tr>
            <td>
                <h1>{{ $company['name'] }}</h1>
                <div class="muted">{{ $company['address'] }} {{ $company['phone'] ? '· '.$company['phone'] : '' }}</div>
            </td>
            <td class="right">
                <h1>Payslip</h1>
                <div class="muted">{{ $run->period_start->format('d-m-Y') }} to {{ $run->period_end->format('d-m-Y') }}</div>
                <div class="muted">{{ $run->reference }}</div>
            </td>
        </tr>
    </table>
</div>

<table class="grid">
    <tr>
        <td><span class="muted">Employee</span><br><strong>{{ $payslip->employee_name }}</strong></td>
        <td><span class="muted">Code</span><br>{{ $payslip->employee_code }}</td>
        <td><span class="muted">Designation</span><br>{{ $payslip->designation ?? '—' }}</td>
        <td><span class="muted">Department</span><br>{{ $payslip->department ?? '—' }}</td>
    </tr>
    <tr>
        <td><span class="muted">Branch</span><br>{{ $payslip->branch ?? '—' }}</td>
        <td><span class="muted">Paid by</span><br>{{ $payslip->payment_method->label() }}</td>
        <td><span class="muted">Bank</span><br>{{ $payslip->bank_name ?? '—' }}</td>
        <td><span class="muted">Account</span><br>{{ $payslip->account_number ?? '—' }}</td>
    </tr>
</table>

<h2>Attendance</h2>
<table class="grid">
    <tr>
        <td><span class="muted">Days in period / employed</span><br>{{ $payslip->period_days }} / {{ $payslip->employed_days }}</td>
        <td><span class="muted">Present / absent</span><br>{{ (float) $payslip->present_days }} / {{ (float) $payslip->absent_days }}</td>
        <td><span class="muted">Half days</span><br>{{ (float) $payslip->half_days }}</td>
        <td><span class="muted">Leave paid / unpaid</span><br>{{ (float) $payslip->paid_leave_days }} / {{ (float) $payslip->unpaid_leave_days }}</td>
    </tr>
    <tr>
        <td><span class="muted">Holidays / weekly offs</span><br>{{ $payslip->holidays }} / {{ $payslip->weekly_offs }}</td>
        <td><span class="muted">Lates</span><br>{{ $payslip->late_count }} ({{ $payslip->late_minutes }} min)</td>
        <td><span class="muted">Overtime</span><br>{{ round(($payslip->overtime_minutes + $payslip->holiday_overtime_minutes) / 60, 2) }} h</td>
        <td><span class="muted">Per day / per hour</span><br>{{ number_format((float) $payslip->per_day_rate, 2) }} / {{ number_format((float) $payslip->per_hour_rate, 2) }}</td>
    </tr>
</table>

<table style="margin-top: 8px;">
    <tr>
        @foreach (['earning' => 'Earnings', 'deduction' => 'Deductions'] as $side => $title)
            <td style="width: 50%; padding: 0 {{ $side === 'earning' ? '8px 0 0' : '0 0 8px' }};">
                <h2>{{ $title }}</h2>
                <table class="lines">
                    <tr><th>Item</th><th class="right">Qty × rate</th><th class="right">Amount</th></tr>
                    @foreach ($payslip->items->where('side', $side) as $item)
                        <tr>
                            <td>{{ $item->name }}</td>
                            <td class="right muted">
                                @if ($item->quantity !== null && $item->rate !== null)
                                    {{ (float) $item->quantity }} {{ $item->unit }} × {{ number_format((float) $item->rate, 2) }}
                                @endif
                            </td>
                            <td class="right">{{ number_format((float) $item->amount, 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="totals">
                        <td colspan="2">Total</td>
                        <td class="right">{{ number_format((float) ($side === 'earning' ? $payslip->earnings_total : $payslip->deductions_total), 2) }}</td>
                    </tr>
                </table>
            </td>
        @endforeach
    </tr>
</table>

<div class="net">
    Net pay: PKR {{ number_format((float) $payslip->net_pay, 2) }}
    @if ((float) $payslip->shortfall > 0)
        <span class="muted" style="font-size: 11px; font-weight: normal;"> · deductions exceeded earnings by {{ number_format((float) $payslip->shortfall, 2) }}</span>
    @endif
</div>

<p class="muted" style="margin-top: 24px;">This is a computer-generated payslip and needs no signature.</p>
</body>
</html>
