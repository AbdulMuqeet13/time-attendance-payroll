import { router, useForm } from '@inertiajs/react';
import { Ban, Lock, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { AttendanceStatusBadge } from '@/components/attendance/attendance-status-badge';
import { FormField } from '@/components/form-field';
import { OptionSelect } from '@/components/option-select';
import { StatusBadge, headline } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatDate } from '@/lib/dates';
import { formatMinutes, shiftLabel } from '@/lib/shifts';
import { formatTime } from '@/lib/time';
import type { AttendanceDay, AttendancePunch } from '@/types';
import {
    decide,
    voidPunch,
} from '@/actions/App/Http/Controllers/Attendance/AttendanceAdjustmentController';
import { punches as punchesRoute } from '@/actions/App/Http/Controllers/Attendance/AttendanceController';

type DayDetailSheetProps = {
    day: AttendanceDay;
    canManage: boolean;
    onAddPunch: () => void;
    onClose: () => void;
};

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="font-medium tabular-nums">{value}</dd>
        </div>
    );
}

/**
 * One employee-day: what the engine computed, every punch behind it, and the manager's corrections.
 */
export function DayDetailSheet({
    day,
    canManage,
    onAddPunch,
    onClose,
}: DayDetailSheetProps) {
    const [punches, setPunches] = useState<AttendancePunch[] | null>(null);
    const isLocked = day.locked_at !== null;
    const isScheduled = day.shift_id !== null;

    useEffect(() => {
        const url = punchesRoute(day.employee_id, {
            query: { date: day.date },
        }).url;

        fetch(url, { headers: { Accept: 'application/json' } })
            .then((response) => response.json())
            .then((payload: { punches: AttendancePunch[] }) =>
                setPunches(payload.punches),
            )
            .catch(() => setPunches([]));
    }, [day.employee_id, day.date, day.id]);

    const { data, setData, post, processing, errors } = useForm({
        status: '',
        waive_late: false,
        reason: day.note ?? '',
    });

    function handleDecision(e: FormEvent) {
        e.preventDefault();
        post(decide(day).url, { preserveScroll: true, onSuccess: onClose });
    }

    function discard(punch: AttendancePunch) {
        const reason = window.prompt('Why discard this punch?');

        if (reason) {
            router.post(
                voidPunch(punch.id).url,
                { reason },
                { preserveScroll: true, onSuccess: onClose },
            );
        }
    }

    return (
        <Sheet open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle className="flex items-center gap-2">
                        {day.employee.name}
                        <AttendanceStatusBadge status={day.status} />
                        {isLocked && (
                            <Lock className="size-4 text-muted-foreground" />
                        )}
                    </SheetTitle>
                    <SheetDescription>
                        {formatDate(day.date)} ·{' '}
                        {day.shift
                            ? shiftLabel(day.shift)
                            : headline(day.day_type)}
                        {isLocked && ' · locked by an approved payroll'}
                    </SheetDescription>
                </SheetHeader>

                <div className="space-y-6 px-4 pb-6">
                    <dl className="grid grid-cols-3 gap-3 text-sm">
                        <Metric
                            label="In"
                            value={formatTime(day.first_in, day.date)}
                        />
                        <Metric
                            label="Out"
                            value={formatTime(day.last_out, day.date)}
                        />
                        <Metric
                            label="Worked"
                            value={formatMinutes(day.worked_minutes)}
                        />
                        <Metric
                            label="Late"
                            value={formatMinutes(day.late_minutes)}
                        />
                        <Metric
                            label="Left early"
                            value={formatMinutes(day.early_leave_minutes)}
                        />
                        <Metric
                            label="Overtime"
                            value={
                                day.overtime_minutes === 0
                                    ? '—'
                                    : `${formatMinutes(day.overtime_minutes)}${day.approved_overtime_minutes === null ? ' (pending)' : ` (${formatMinutes(day.approved_overtime_minutes)} approved)`}`
                            }
                        />
                    </dl>

                    {day.is_missing_checkout && (
                        <StatusBadge tone="danger">
                            No check-out: credited to shift end
                        </StatusBadge>
                    )}
                    {day.note && (
                        <p className="rounded-md bg-muted p-2 text-sm">
                            Note: {day.note}
                        </p>
                    )}

                    <div>
                        <div className="mb-2 flex items-center justify-between">
                            <p className="text-sm font-medium">Punches</p>
                            {canManage && !isLocked && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={onAddPunch}
                                >
                                    <Plus className="mr-1 size-3.5" />
                                    Add punch
                                </Button>
                            )}
                        </div>
                        {punches === null ? (
                            <p className="animate-pulse text-sm text-muted-foreground">
                                Loading punches...
                            </p>
                        ) : punches.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No punches around this day.
                            </p>
                        ) : (
                            <ul className="divide-y rounded-md border text-sm">
                                {punches.map((punch) => (
                                    <li
                                        key={punch.id}
                                        className="flex items-center gap-3 px-3 py-2"
                                    >
                                        <span
                                            className={
                                                punch.voided_at
                                                    ? 'font-mono text-muted-foreground line-through'
                                                    : 'font-mono'
                                            }
                                        >
                                            {formatTime(
                                                punch.punched_at,
                                                day.date,
                                            )}
                                        </span>
                                        <span className="min-w-0 flex-1 truncate text-xs text-muted-foreground">
                                            {punch.source === 'device'
                                                ? (punch.device?.name ??
                                                  'Device')
                                                : `${headline(punch.source)} by ${punch.creator?.name ?? 'system'}: ${punch.reason ?? ''}`}
                                            {punch.voided_at &&
                                                ` · discarded: ${punch.void_reason}`}
                                        </span>
                                        {canManage &&
                                            !isLocked &&
                                            !punch.voided_at && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-7"
                                                    onClick={() =>
                                                        discard(punch)
                                                    }
                                                >
                                                    <Ban className="size-3.5" />
                                                    <span className="sr-only">
                                                        Discard punch
                                                    </span>
                                                </Button>
                                            )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    {canManage && !isLocked && isScheduled && (
                        <>
                            <Separator />
                            <form
                                onSubmit={handleDecision}
                                className="space-y-4"
                            >
                                <p className="text-sm font-medium">
                                    Correct this day
                                </p>
                                <FormField
                                    label="Set status"
                                    htmlFor="decision-status"
                                    error={errors.status}
                                >
                                    <OptionSelect
                                        id="decision-status"
                                        value={data.status}
                                        onChange={(value) =>
                                            setData('status', value)
                                        }
                                        options={[
                                            {
                                                value: 'present',
                                                label: 'Present',
                                            },
                                            {
                                                value: 'half_day',
                                                label: 'Half Day',
                                            },
                                            {
                                                value: 'absent',
                                                label: 'Absent',
                                            },
                                        ]}
                                        noneLabel="As calculated"
                                        placeholder="As calculated"
                                    />
                                </FormField>
                                {day.status === 'late' && (
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="waive-late"
                                            checked={data.waive_late}
                                            onCheckedChange={(checked) =>
                                                setData(
                                                    'waive_late',
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <Label htmlFor="waive-late">
                                            Excuse the late arrival
                                        </Label>
                                    </div>
                                )}
                                <FormField
                                    label="Reason"
                                    htmlFor="decision-reason"
                                    error={errors.reason}
                                    required
                                >
                                    <Input
                                        id="decision-reason"
                                        value={data.reason}
                                        onChange={(e) =>
                                            setData('reason', e.target.value)
                                        }
                                        required
                                    />
                                </FormField>
                                <Button type="submit" disabled={processing}>
                                    Save correction
                                </Button>
                            </form>
                        </>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}
