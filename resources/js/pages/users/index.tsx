import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { TableColumn } from '@/components/data-table';
import {
    DataTable,
    DataTableSearch,
    DataTableToolbar,
} from '@/components/data-table';
import Heading from '@/components/heading';
import { RowActions } from '@/components/organisation/row-actions';
import { ActiveBadge } from '@/components/status-badge';
import type { UserRow } from '@/components/users/user-form-dialog';
import { UserFormDialog } from '@/components/users/user-form-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useDataTable } from '@/hooks/use-data-table';
import type { EmployeeOption, Option, Paginated } from '@/types';
import { index } from '@/actions/App/Http/Controllers/Users/UserController';
import { dashboard } from '@/routes';

type UsersPageProps = {
    users: Paginated<UserRow>;
    roles: string[];
    branches: Option[];
    employeesWithoutLogin: (EmployeeOption & { email: string | null })[];
};

export default function Users({
    users,
    roles,
    branches,
    employeesWithoutLogin,
}: UsersPageProps) {
    const table = useDataTable({ only: ['users'], defaultPerPage: 25 });
    const [isCreating, setIsCreating] = useState(false);
    const [editing, setEditing] = useState<UserRow | null>(null);

    const columns: TableColumn<UserRow>[] = [
        {
            id: 'name',
            header: () => <span>User</span>,
            cell: ({ row }) => (
                <div>
                    <div className="font-medium">{row.original.name}</div>
                    <div className="text-xs text-muted-foreground">
                        {row.original.email}
                    </div>
                </div>
            ),
        },
        {
            id: 'roles',
            header: () => <span>Roles</span>,
            cell: ({ row }) => (
                <div className="flex flex-wrap gap-1">
                    {row.original.roles.map((role) => (
                        <Badge key={role} variant="secondary">
                            {role}
                        </Badge>
                    ))}
                </div>
            ),
        },
        {
            id: 'branch',
            header: () => <span>Branch</span>,
            cell: ({ row }) => row.original.branch?.name ?? 'All branches',
        },
        {
            id: 'employee',
            header: () => <span>Employee record</span>,
            cell: ({ row }) =>
                row.original.employee
                    ? `${row.original.employee.name} (${row.original.employee.employee_code})`
                    : '—',
        },
        {
            id: 'active',
            header: () => <span>Status</span>,
            cell: ({ row }) => <ActiveBadge active={row.original.is_active} />,
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            cell: ({ row }) => (
                <RowActions onEdit={() => setEditing(row.original)} />
            ),
        },
    ];

    return (
        <>
            <Head title="Users" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Users & Access"
                    description="Who can sign in and what they can do."
                />
                <DataTable
                    columns={columns}
                    data={users.data}
                    meta={users}
                    onPageChange={(page) =>
                        router.reload({ only: ['users'], data: { page } })
                    }
                    toolbar={
                        <DataTableToolbar>
                            <DataTableSearch
                                value={table.search}
                                onChange={table.setSearch}
                                placeholder="Name or email..."
                                className="w-full sm:w-64"
                            />
                            <Button
                                size="sm"
                                onClick={() => setIsCreating(true)}
                            >
                                <Plus className="mr-2 size-4" /> Add Account
                            </Button>
                        </DataTableToolbar>
                    }
                />
            </div>
            {(isCreating || editing) && (
                <UserFormDialog
                    user={editing}
                    roles={roles}
                    branches={branches}
                    employees={employeesWithoutLogin}
                    onClose={() => {
                        setIsCreating(false);
                        setEditing(null);
                    }}
                />
            )}
        </>
    );
}

Users.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Users', href: index().url },
    ],
};
