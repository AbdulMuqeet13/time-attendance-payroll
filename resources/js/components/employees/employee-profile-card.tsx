import type { ReactNode } from 'react';
import { headline } from '@/components/status-badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/dates';
import type { Employee } from '@/types';

function Detail({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="truncate text-sm">{children || '—'}</dd>
        </div>
    );
}

export function EmployeeProfileCard({ employee }: { employee: Employee }) {
    return (
        <div className="grid gap-4 lg:grid-cols-3">
            <Card>
                <CardHeader>
                    <CardTitle>Personal</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl className="grid grid-cols-2 gap-3">
                        <Detail label="Father's name">
                            {employee.father_name}
                        </Detail>
                        <Detail label="CNIC">{employee.cnic}</Detail>
                        <Detail label="Gender">
                            {headline(employee.gender)}
                        </Detail>
                        <Detail label="Date of birth">
                            {formatDate(employee.date_of_birth)}
                        </Detail>
                        <Detail label="Phone">{employee.phone}</Detail>
                        <Detail label="Email">{employee.email}</Detail>
                        <div className="col-span-2">
                            <Detail label="Address">{employee.address}</Detail>
                        </div>
                    </dl>
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Employment</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl className="grid grid-cols-2 gap-3">
                        <Detail label="Branch">{employee.branch?.name}</Detail>
                        <Detail label="Department">
                            {employee.department?.name}
                        </Detail>
                        <Detail label="Designation">
                            {employee.designation?.name}
                        </Detail>
                        <Detail label="Reports to">
                            {employee.manager?.name}
                        </Detail>
                        <Detail label="Type">
                            {headline(employee.employment_type)}
                        </Detail>
                        <Detail label="Joined">
                            {formatDate(employee.joining_date)}
                        </Detail>
                        <Detail label="Confirmed">
                            {formatDate(employee.confirmation_date)}
                        </Detail>
                        <Detail label="Exit date">
                            {formatDate(employee.exit_date)}
                        </Detail>
                    </dl>
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Payment & access</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl className="grid grid-cols-2 gap-3">
                        <Detail label="Paid by">
                            {headline(employee.payment_method)}
                        </Detail>
                        <Detail label="Bank">{employee.bank_name}</Detail>
                        <Detail label="Account title">
                            {employee.account_title}
                        </Detail>
                        <Detail label="Account / IBAN">
                            {employee.account_number}
                        </Detail>
                        <Detail label="Device PIN">
                            <span className="font-mono">
                                {employee.device_pin}
                            </span>
                        </Detail>
                        <Detail label="Login">{employee.user?.email}</Detail>
                    </dl>
                </CardContent>
            </Card>
        </div>
    );
}
