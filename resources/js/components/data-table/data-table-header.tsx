import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { SortState } from '@/types';
import { cn } from '@/lib/utils';

type DataTableSortHeaderProps = {
    column: string;
    label: string;
    sort?: SortState | null;
    onSort?: (column: string) => void;
    className?: string;
};

export function DataTableSortHeader({
    column,
    label,
    sort,
    onSort,
    className,
}: DataTableSortHeaderProps) {
    if (!onSort) {
        return <span className={className}>{label}</span>;
    }

    const isActive = sort?.column === column;
    const direction = isActive ? sort.direction : null;

    return (
        <Button
            variant="ghost"
            size="sm"
            className={cn('-ml-3 h-8', className)}
            onClick={() => onSort(column)}
        >
            <span>{label}</span>
            {direction === 'asc' ? (
                <ArrowUp className="ml-1 size-3.5" />
            ) : direction === 'desc' ? (
                <ArrowDown className="ml-1 size-3.5" />
            ) : (
                <ArrowUpDown className="ml-1 size-3.5 text-muted-foreground/50" />
            )}
        </Button>
    );
}
