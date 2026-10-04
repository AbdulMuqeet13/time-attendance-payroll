import { router } from '@inertiajs/react';
import { Fingerprint, ScanFace, Trash2, Upload, UserMinus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { BadgeTone } from '@/components/status-badge';
import { StatusBadge, headline } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { formatDateTime, timeAgo } from '@/lib/dates';
import type { Employee, EmployeeBiometrics, EnrollmentStatus } from '@/types';
import {
    destroyTemplate,
    push,
    remove,
} from '@/actions/App/Http/Controllers/Employees/EmployeeBiometricController';

const FINGERS = [
    'Left little',
    'Left ring',
    'Left middle',
    'Left index',
    'Left thumb',
    'Right thumb',
    'Right index',
    'Right middle',
    'Right ring',
    'Right little',
];

const enrollmentTones: Record<EnrollmentStatus, BadgeTone> = {
    queued: 'info',
    on_device: 'success',
    removing: 'warning',
    removed: 'neutral',
    failed: 'danger',
};

type BiometricsCardProps = {
    employee: Employee;
    biometrics: EmployeeBiometrics;
};

export function BiometricsCard({ employee, biometrics }: BiometricsCardProps) {
    const [mode, setMode] = useState<'push' | 'remove' | null>(null);
    const [selected, setSelected] = useState<number[]>([]);
    const [deletingTemplate, setDeletingTemplate] = useState<number | null>(
        null,
    );

    const open = (next: 'push' | 'remove') => {
        setSelected(
            next === 'remove'
                ? biometrics.enrollments
                      .filter((enrollment) => enrollment.status !== 'removed')
                      .map((enrollment) => enrollment.device_id)
                : biometrics.devices.map((device) => device.id),
        );
        setMode(next);
    };

    const submit = () => {
        const url = mode === 'push' ? push(employee).url : remove(employee).url;
        router.post(
            url,
            { device_ids: selected },
            { preserveScroll: true, onSuccess: () => setMode(null) },
        );
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div className="space-y-1.5">
                    <CardTitle>Biometrics & devices</CardTitle>
                    <CardDescription>
                        {employee.device_pin
                            ? `Device PIN ${employee.device_pin}. Enrol once on any device, then push to the others.`
                            : 'Set a device PIN on the profile before enrolling.'}
                    </CardDescription>
                </div>
                <div className="flex gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => open('remove')}
                        disabled={biometrics.enrollments.length === 0}
                    >
                        <UserMinus className="mr-2 size-4" />
                        Remove
                    </Button>
                    <Button
                        size="sm"
                        onClick={() => open('push')}
                        disabled={
                            !employee.device_pin ||
                            biometrics.devices.length === 0
                        }
                    >
                        <Upload className="mr-2 size-4" />
                        Enrol / push to devices
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="grid gap-6 lg:grid-cols-2">
                <div>
                    <p className="mb-2 text-sm font-medium">Stored templates</p>
                    {biometrics.templates.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            None yet. Push the employee to a device and have
                            them scan a finger or face; the device sends the
                            template here.
                        </p>
                    ) : (
                        <ul className="divide-y rounded-md border">
                            {biometrics.templates.map((template) => (
                                <li
                                    key={template.id}
                                    className="flex items-center gap-3 px-3 py-2 text-sm"
                                >
                                    {template.type === 'face' ? (
                                        <ScanFace className="size-4 text-muted-foreground" />
                                    ) : (
                                        <Fingerprint className="size-4 text-muted-foreground" />
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <div className="font-medium">
                                            {template.type === 'fingerprint'
                                                ? (FINGERS[
                                                      template.finger_index
                                                  ] ??
                                                  `Finger ${template.finger_index}`)
                                                : headline(template.type)}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {template.source_device?.name ??
                                                'Unknown device'}{' '}
                                            ·{' '}
                                            {formatDateTime(
                                                template.captured_at,
                                            )}
                                            {template.major_version &&
                                                ` · algorithm v${template.major_version}`}
                                        </div>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="size-7"
                                        onClick={() =>
                                            setDeletingTemplate(template.id)
                                        }
                                    >
                                        <Trash2 className="size-3.5" />
                                        <span className="sr-only">
                                            Delete template
                                        </span>
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
                <div>
                    <p className="mb-2 text-sm font-medium">On devices</p>
                    {biometrics.enrollments.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Not on any device yet.
                        </p>
                    ) : (
                        <ul className="divide-y rounded-md border">
                            {biometrics.enrollments.map((enrollment) => (
                                <li
                                    key={enrollment.id}
                                    className="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                                >
                                    <div className="min-w-0">
                                        <div className="font-medium">
                                            {enrollment.device.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {enrollment.message ??
                                                (enrollment.synced_at
                                                    ? `Synced ${timeAgo(enrollment.synced_at)}`
                                                    : 'Waiting for the device')}
                                        </div>
                                    </div>
                                    <StatusBadge
                                        tone={
                                            enrollmentTones[enrollment.status]
                                        }
                                    >
                                        {headline(enrollment.status)}
                                    </StatusBadge>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </CardContent>

            {mode && (
                <Dialog
                    open
                    onOpenChange={(isOpen) => !isOpen && setMode(null)}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {mode === 'push'
                                    ? 'Enrol / push to devices'
                                    : 'Remove from devices'}
                            </DialogTitle>
                            <DialogDescription>
                                {mode === 'push'
                                    ? 'Creates the employee on each device with every stored fingerprint and face. With no templates yet, they can enrol by scanning on any of these devices.'
                                    : 'Deletes the employee and their biometrics from each device. Stored templates and past scans are kept.'}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-2">
                            {biometrics.devices.map((device) => (
                                <div
                                    key={device.id}
                                    className="flex items-center gap-2"
                                >
                                    <Checkbox
                                        id={`device-${device.id}`}
                                        checked={selected.includes(device.id)}
                                        onCheckedChange={(checked) =>
                                            setSelected((current) =>
                                                checked
                                                    ? [...current, device.id]
                                                    : current.filter(
                                                          (id) =>
                                                              id !== device.id,
                                                      ),
                                            )
                                        }
                                    />
                                    <Label htmlFor={`device-${device.id}`}>
                                        {device.name}
                                        <span className="ml-2 text-xs text-muted-foreground">
                                            last seen{' '}
                                            {timeAgo(device.last_seen_at)}
                                        </span>
                                    </Label>
                                </div>
                            ))}
                        </div>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setMode(null)}
                            >
                                Cancel
                            </Button>
                            <Button
                                variant={
                                    mode === 'remove'
                                        ? 'destructive'
                                        : 'default'
                                }
                                onClick={submit}
                                disabled={selected.length === 0}
                            >
                                {mode === 'push' ? 'Push' : 'Remove'} (
                                {selected.length})
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            )}

            {deletingTemplate !== null && (
                <ConfirmDialog
                    open
                    onClose={() => setDeletingTemplate(null)}
                    title="Delete Stored Template"
                    description="Removes our stored copy. Devices that already have it keep it until the employee is removed from them."
                    url={destroyTemplate([employee, deletingTemplate]).url}
                />
            )}
        </Card>
    );
}
