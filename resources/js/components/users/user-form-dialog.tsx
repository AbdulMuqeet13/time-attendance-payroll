import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { EmployeeMultiSelect } from '@/components/employee-multi-select';
import { FormField } from '@/components/form-field';
import { OptionSelect, toOptions } from '@/components/option-select';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { EmployeeOption, Option } from '@/types';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Users/UserController';

export type UserRow = {
    id: number;
    name: string;
    email: string;
    branch_id: number | null;
    is_active: boolean;
    branch: Option | null;
    roles: string[];
    employee: EmployeeOption | null;
};

type UserFormDialogProps = {
    user: UserRow | null;
    roles: string[];
    branches: Option[];
    employees: (EmployeeOption & { email: string | null })[];
    onClose: () => void;
};

export function UserFormDialog({
    user,
    roles,
    branches,
    employees,
    onClose,
}: UserFormDialogProps) {
    const { data, setData, transform, post, put, processing, errors } = useForm(
        {
            name: user?.name ?? '',
            email: user?.email ?? '',
            password: '',
            password_confirmation: '',
            roles: user?.roles ?? ['Employee'],
            branch_id: String(user?.branch_id ?? ''),
            employee_ids: [] as number[],
            is_active: user?.is_active ?? true,
        },
    );

    transform((values) => ({
        ...values,
        branch_id: values.branch_id || null,
        employee_id: values.employee_ids[0] ?? null,
    }));

    const fieldErrors = errors as Record<string, string | undefined>;

    function handleSubmit(e: FormEvent) {
        e.preventDefault();

        if (user) {
            put(update(user.id).url, {
                preserveScroll: true,
                onSuccess: onClose,
            });
        } else {
            post(store().url, { preserveScroll: true, onSuccess: onClose });
        }
    }

    function pickEmployee(ids: number[]) {
        const employee = employees.find((candidate) => candidate.id === ids[0]);
        setData((current) => ({
            ...current,
            employee_ids: ids,
            name: current.name || employee?.name || '',
            email: current.email || employee?.email || '',
        }));
    }

    return (
        <Dialog open onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {user ? 'Edit Account' : 'Add Account'}
                    </DialogTitle>
                    <DialogDescription>
                        Choose "Employee" for self-service logins and link the
                        employee record. A branch limits the user to that
                        branch.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {!user && (
                        <FormField
                            label="Employee (optional)"
                            htmlFor="user-employee"
                            error={fieldErrors.employee_id}
                        >
                            <EmployeeMultiSelect
                                id="user-employee"
                                single
                                employees={employees}
                                value={data.employee_ids}
                                onChange={pickEmployee}
                                placeholder="Link an employee..."
                            />
                        </FormField>
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Name"
                            htmlFor="user-name"
                            error={errors.name}
                            required
                        >
                            <Input
                                id="user-name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            label="Email"
                            htmlFor="user-email"
                            error={errors.email}
                            required
                        >
                            <Input
                                id="user-email"
                                type="email"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            label={user ? 'New password' : 'Password'}
                            htmlFor="user-password"
                            error={errors.password}
                            required={!user}
                        >
                            <Input
                                id="user-password"
                                type="password"
                                autoComplete="new-password"
                                value={data.password}
                                onChange={(e) =>
                                    setData('password', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Confirm password"
                            htmlFor="user-password-confirmation"
                        >
                            <Input
                                id="user-password-confirmation"
                                type="password"
                                autoComplete="new-password"
                                value={data.password_confirmation}
                                onChange={(e) =>
                                    setData(
                                        'password_confirmation',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>
                    </div>
                    <FormField label="Roles" error={errors.roles} required>
                        <div className="grid grid-cols-2 gap-2">
                            {roles.map((role) => (
                                <div
                                    key={role}
                                    className="flex items-center gap-2"
                                >
                                    <Checkbox
                                        id={`role-${role}`}
                                        checked={data.roles.includes(role)}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'roles',
                                                checked
                                                    ? [...data.roles, role]
                                                    : data.roles.filter(
                                                          (existing) =>
                                                              existing !== role,
                                                      ),
                                            )
                                        }
                                    />
                                    <Label htmlFor={`role-${role}`}>
                                        {role}
                                    </Label>
                                </div>
                            ))}
                        </div>
                    </FormField>
                    <FormField
                        label="Branch"
                        htmlFor="user-branch"
                        error={errors.branch_id}
                        hint="Leave empty for access to every branch."
                    >
                        <OptionSelect
                            id="user-branch"
                            value={data.branch_id}
                            onChange={(value) => setData('branch_id', value)}
                            options={toOptions(branches)}
                            noneLabel="All branches"
                            placeholder="All branches"
                        />
                    </FormField>
                    {user && (
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="user-active"
                                checked={data.is_active}
                                onCheckedChange={(checked) =>
                                    setData('is_active', checked === true)
                                }
                            />
                            <Label htmlFor="user-active">
                                Active (can sign in)
                            </Label>
                        </div>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
