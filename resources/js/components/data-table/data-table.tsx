import {
    type ColumnDef,
    type RowData,
    tableFeatures,
    useTable,
} from '@tanstack/react-table';

import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { PaginationMeta } from '@/types';
import { DataTableEmpty } from './data-table-empty';
import { DataTablePagination } from './data-table-pagination';
import { DataTableSkeleton } from './data-table-skeleton';

const features = tableFeatures({});

/**
 * Column definition for DataTable rows of type T.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type TableColumn<T extends RowData> = ColumnDef<typeof features, T, any>;

type DataTableProps<TData extends RowData> = {
    columns: TableColumn<TData>[];
    data: TData[];
    meta?: PaginationMeta;
    onPageChange?: (page: number) => void;
    onPerPageChange?: (perPage: number) => void;
    isLoading?: boolean;
    emptyMessage?: string;
    emptyDescription?: string;
    toolbar?: React.ReactNode;
};

export function DataTable<TData extends RowData>({
    columns,
    data,
    meta,
    onPageChange,
    onPerPageChange,
    isLoading = false,
    emptyMessage = 'No results found.',
    emptyDescription = 'Try adjusting your search or filters.',
    toolbar,
}: DataTableProps<TData>) {
    const table = useTable({
        features,
        data,
        columns,
    });

    return (
        <div className="space-y-4">
            {toolbar}

            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => (
                                    <TableHead key={header.id}>
                                        {header.isPlaceholder ? null : (
                                            <table.FlexRender header={header} />
                                        )}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {isLoading ? (
                            <DataTableSkeleton columnCount={columns.length} />
                        ) : table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow key={row.id}>
                                    {row.getAllCells().map((cell) => (
                                        <TableCell key={cell.id}>
                                            <table.FlexRender cell={cell} />
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length}
                                    className="h-48"
                                >
                                    <DataTableEmpty
                                        message={emptyMessage}
                                        description={emptyDescription}
                                    />
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {meta && (
                <DataTablePagination
                    meta={meta}
                    onPageChange={onPageChange}
                    onPerPageChange={onPerPageChange}
                />
            )}
        </div>
    );
}
