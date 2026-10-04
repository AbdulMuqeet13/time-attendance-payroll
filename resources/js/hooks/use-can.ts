import { usePage } from '@inertiajs/react';
import { useCallback, useMemo } from 'react';
import type { Permission, Role } from '@/types';

type UseCanReturn = {
    can: (permission: Permission) => boolean;
    canAny: (permissions: Permission[]) => boolean;
    hasRole: (role: Role) => boolean;
    hasAnyRole: (roles: Role[]) => boolean;
    permissions: string[];
    roles: string[];
};

export function useCan(): UseCanReturn {
    const { auth } = usePage().props;
    const permissions = (auth.permissions ?? []) as string[];
    const roles = (auth.roles ?? []) as string[];

    const can = useCallback(
        (permission: Permission): boolean => permissions.includes(permission),
        [permissions],
    );

    const canAny = useCallback(
        (perms: Permission[]): boolean =>
            perms.some((p) => permissions.includes(p)),
        [permissions],
    );

    const hasRole = useCallback(
        (role: Role): boolean => roles.includes(role),
        [roles],
    );

    const hasAnyRole = useCallback(
        (roleList: Role[]): boolean => roleList.some((r) => roles.includes(r)),
        [roles],
    );

    return useMemo(
        () => ({ can, canAny, hasRole, hasAnyRole, permissions, roles }),
        [can, canAny, hasRole, hasAnyRole, permissions, roles],
    );
}
