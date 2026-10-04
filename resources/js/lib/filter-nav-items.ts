import type { NavItem } from '@/types';

export function filterNavByPermissions(
    items: NavItem[],
    permissions: string[],
): NavItem[] {
    return items
        .filter((item) => {
            if (!item.permission) {
                return true;
            }

            return permissions.includes(item.permission);
        })
        .map((item) => {
            if (!item.children?.length) {
                return item;
            }

            const filteredChildren = filterNavByPermissions(
                item.children,
                permissions,
            );

            if (filteredChildren.length === 0) {
                return null;
            }

            return { ...item, children: filteredChildren };
        })
        .filter((item): item is NavItem => item !== null);
}
