import { router } from '@inertiajs/react';
import { Fingerprint, ScanFace } from 'lucide-react';
import { useEffect, useState } from 'react';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { DeviceUserRow } from '@/types';

type DeviceUsersTableProps = {
    rows: DeviceUserRow[] | undefined;
    onLinkPin: (pin: string) => void;
};

/**
 * Users the device reported, matched to employees by PIN, plus employees that should be on it but aren't.
 */
export function DeviceUsersTable({ rows, onLinkPin }: DeviceUsersTableProps) {
    const [requested, setRequested] = useState(false);

    useEffect(() => {
        if (rows === undefined && !requested) {
            setRequested(true);
            router.reload({ only: ['deviceUsers', 'employeesWithoutPin'] });
        }
    }, [rows, requested]);

    if (rows === undefined) {
        return (
            <div className="space-y-2">
                <Skeleton className="h-8 w-full animate-pulse" />
                <Skeleton className="h-8 w-full animate-pulse" />
                <Skeleton className="h-8 w-full animate-pulse" />
            </div>
        );
    }

    if (rows.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                The device hasn't reported its users yet. Use Actions → Re-read
                users & biometrics.
            </p>
        );
    }

    return (
        <div className="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>PIN</TableHead>
                        <TableHead>Name on device</TableHead>
                        <TableHead>Biometrics</TableHead>
                        <TableHead>Employee</TableHead>
                        <TableHead>State</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((row) => (
                        <TableRow key={`${row.state}-${row.pin}`}>
                            <TableCell className="font-mono">
                                {row.pin}
                                {row.privilege >= 14 && (
                                    <StatusBadge
                                        tone="warning"
                                        className="ml-2 font-sans"
                                    >
                                        Admin
                                    </StatusBadge>
                                )}
                            </TableCell>
                            <TableCell>{row.device_name ?? '—'}</TableCell>
                            <TableCell>
                                <span className="inline-flex items-center gap-2 text-xs text-muted-foreground">
                                    <Fingerprint className="size-3.5" />
                                    {row.fingerprint_count}
                                    {row.has_face && (
                                        <ScanFace className="size-3.5" />
                                    )}
                                </span>
                            </TableCell>
                            <TableCell>
                                {row.employee
                                    ? `${row.employee.name} (${row.employee.employee_code})`
                                    : '—'}
                            </TableCell>
                            <TableCell>
                                {row.state === 'linked' && (
                                    <StatusBadge tone="success">
                                        Linked
                                    </StatusBadge>
                                )}
                                {row.state === 'missing' && (
                                    <StatusBadge tone="danger">
                                        Missing on device
                                    </StatusBadge>
                                )}
                                {row.state === 'unknown' && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => onLinkPin(row.pin)}
                                    >
                                        Unknown PIN · link
                                    </Button>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
