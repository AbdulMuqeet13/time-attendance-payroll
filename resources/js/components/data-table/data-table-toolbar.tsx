import type { ReactNode } from 'react';

type DataTableToolbarProps = {
    children: ReactNode;
};

export function DataTableToolbar({ children }: DataTableToolbarProps) {
    return (
        <div className="flex items-center justify-between gap-2">
            {children}
        </div>
    );
}
