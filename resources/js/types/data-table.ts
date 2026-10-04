import type { ReactNode } from 'react';

export type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type PaginatedResponse<T> = {
    data: T[];
    meta: PaginationMeta;
};

export type SortState = {
    column: string;
    direction: 'asc' | 'desc';
};

export type FilterState = Record<string, string | string[] | undefined>;

export type DataTableColumn<T> = {
    key: string;
    label: string;
    sortable?: boolean;
    className?: string;
    render?: (value: unknown, row: T) => ReactNode;
};

/**
 * A Laravel LengthAwarePaginator serialized to JSON: the records plus the pagination meta at the top level.
 */
export type Paginated<T> = PaginationMeta & {
    data: T[];
};
