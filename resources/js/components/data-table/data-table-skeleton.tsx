import { Skeleton } from '@/components/ui/skeleton';
import { TableCell, TableRow } from '@/components/ui/table';

type DataTableSkeletonProps = {
    columnCount: number;
    rowCount?: number;
};

export function DataTableSkeleton({
    columnCount,
    rowCount = 5,
}: DataTableSkeletonProps) {
    return (
        <>
            {Array.from({ length: rowCount }).map((_, rowIndex) => (
                <TableRow key={rowIndex}>
                    {Array.from({ length: columnCount }).map((_, cellIndex) => (
                        <TableCell key={cellIndex}>
                            <Skeleton className="h-5 w-full" />
                        </TableCell>
                    ))}
                </TableRow>
            ))}
        </>
    );
}
