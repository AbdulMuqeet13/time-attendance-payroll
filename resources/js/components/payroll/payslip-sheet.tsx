import { Download } from 'lucide-react';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatMinutes } from '@/lib/shifts';
import { formatAmount } from '@/lib/utils';
import type { PayrollRun, Payslip } from '@/types';
import { payslipPdf } from '@/actions/App/Http/Controllers/Payroll/PayrollRunController';

function Lines({
    payslip,
    side,
}: {
    payslip: Payslip;
    side: 'earning' | 'deduction';
}) {
    const items = payslip.items.filter((item) => item.side === side);

    return (
        <table className="w-full text-sm">
            <tbody>
                {items.map((item) => (
                    <tr key={item.id} className="border-b last:border-0">
                        <td className="py-1.5">
                            {item.name}
                            {item.quantity !== null && item.rate !== null && (
                                <div className="text-xs text-muted-foreground">
                                    {Number(item.quantity)} {item.unit} ×{' '}
                                    {formatAmount(item.rate)}
                                </div>
                            )}
                        </td>
                        <td className="py-1.5 text-right tabular-nums">
                            {formatAmount(item.amount)}
                        </td>
                    </tr>
                ))}
                {items.length === 0 && (
                    <tr>
                        <td className="py-1.5 text-muted-foreground">None</td>
                    </tr>
                )}
            </tbody>
        </table>
    );
}

export function PayslipSheet({
    run,
    payslip,
    onClose,
}: {
    run: PayrollRun;
    payslip: Payslip;
    onClose: () => void;
}) {
    return (
        <Sheet open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{payslip.employee_name}</SheetTitle>
                    <SheetDescription>
                        {payslip.employee_code} · {payslip.designation ?? '—'} ·{' '}
                        {run.reference}
                    </SheetDescription>
                </SheetHeader>
                <div className="space-y-5 px-4 pb-6">
                    <div className="grid grid-cols-3 gap-3 text-sm">
                        {[
                            [
                                'Days employed',
                                `${payslip.employed_days} / ${payslip.period_days}`,
                            ],
                            ['Present', Number(payslip.present_days)],
                            ['Absent', Number(payslip.absent_days)],
                            ['Half days', Number(payslip.half_days)],
                            [
                                'Leave (paid/unpaid)',
                                `${Number(payslip.paid_leave_days)} / ${Number(payslip.unpaid_leave_days)}`,
                            ],
                            [
                                'Lates',
                                `${payslip.late_count} (${formatMinutes(payslip.late_minutes)})`,
                            ],
                            [
                                'Overtime',
                                formatMinutes(payslip.overtime_minutes),
                            ],
                            [
                                'Holiday overtime',
                                formatMinutes(payslip.holiday_overtime_minutes),
                            ],
                            ['Per day', formatAmount(payslip.per_day_rate)],
                        ].map(([label, value]) => (
                            <div key={String(label)}>
                                <div className="text-xs text-muted-foreground">
                                    {label}
                                </div>
                                <div className="font-medium tabular-nums">
                                    {value}
                                </div>
                            </div>
                        ))}
                    </div>
                    <div>
                        <p className="mb-1 text-sm font-semibold">
                            Earnings · {formatAmount(payslip.earnings_total)}
                        </p>
                        <Lines payslip={payslip} side="earning" />
                    </div>
                    <div>
                        <p className="mb-1 text-sm font-semibold">
                            Deductions ·{' '}
                            {formatAmount(payslip.deductions_total)}
                        </p>
                        <Lines payslip={payslip} side="deduction" />
                    </div>
                    <div className="flex items-center justify-between rounded-lg bg-muted p-3">
                        <span className="font-semibold">Net pay</span>
                        <span className="text-lg font-semibold tabular-nums">
                            {formatAmount(payslip.net_pay)}
                        </span>
                    </div>
                    {Number(payslip.shortfall) > 0 && (
                        <StatusBadge tone="danger">
                            Deductions exceeded earnings by{' '}
                            {formatAmount(payslip.shortfall)}
                        </StatusBadge>
                    )}
                    <Button variant="outline" asChild className="w-full">
                        <a href={payslipPdf([run, payslip]).url}>
                            <Download className="mr-2 size-4" /> Download PDF
                        </a>
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}
