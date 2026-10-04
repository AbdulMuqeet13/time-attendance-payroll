import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { PaginationMeta } from '@/types';

type DataTablePaginationProps = {
    meta: PaginationMeta;
    onPageChange?: (page: number) => void;
    onPerPageChange?: (perPage: number) => void;
    pageSizeOptions?: number[];
};

export function DataTablePagination({
    meta,
    onPageChange,
    onPerPageChange,
    pageSizeOptions = [10, 25, 50, 100],
}: DataTablePaginationProps) {
    const { current_page, last_page, per_page, total, from, to } = meta;

    return (
        <div className="flex items-center justify-between px-2">
            <div className="flex-1 text-sm text-muted-foreground">
                {from && to ? (
                    <span>
                        Showing {from} to {to} of {total} results
                    </span>
                ) : (
                    <span>No results</span>
                )}
            </div>
            <div className="flex items-center space-x-6 lg:space-x-8">
                <div className="flex items-center space-x-2">
                    <p className="text-sm font-medium">Rows per page</p>
                    <Select
                        value={String(per_page)}
                        onValueChange={(value) =>
                            onPerPageChange?.(Number(value))
                        }
                    >
                        <SelectTrigger className="h-8 w-[70px]">
                            <SelectValue placeholder={String(per_page)} />
                        </SelectTrigger>
                        <SelectContent side="top">
                            {pageSizeOptions.map((size) => (
                                <SelectItem key={size} value={String(size)}>
                                    {size}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="flex w-[100px] items-center justify-center text-sm font-medium">
                    Page {current_page} of {last_page}
                </div>
                <div className="flex items-center space-x-2">
                    <Button
                        variant="outline"
                        className="hidden size-8 p-0 lg:flex"
                        onClick={() => onPageChange?.(1)}
                        disabled={current_page <= 1}
                    >
                        <span className="sr-only">Go to first page</span>
                        <ChevronsLeft className="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        className="size-8 p-0"
                        onClick={() => onPageChange?.(current_page - 1)}
                        disabled={current_page <= 1}
                    >
                        <span className="sr-only">Go to previous page</span>
                        <ChevronLeft className="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        className="size-8 p-0"
                        onClick={() => onPageChange?.(current_page + 1)}
                        disabled={current_page >= last_page}
                    >
                        <span className="sr-only">Go to next page</span>
                        <ChevronRight className="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        className="hidden size-8 p-0 lg:flex"
                        onClick={() => onPageChange?.(last_page)}
                        disabled={current_page >= last_page}
                    >
                        <span className="sr-only">Go to last page</span>
                        <ChevronsRight className="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    );
}
