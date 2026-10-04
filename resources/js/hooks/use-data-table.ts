import { router, usePage } from '@inertiajs/react';
import { useCallback, useMemo, useRef } from 'react';
import type { FilterState, SortState } from '@/types';

type ReloadData = NonNullable<
    NonNullable<Parameters<typeof router.reload>[0]>['data']
>;

type UseDataTableOptions = {
    only: string[];
    defaultPerPage?: number;
};

type UseDataTableReturn = {
    search: string;
    sort: SortState | null;
    filters: FilterState;
    page: number;
    perPage: number;
    setSearch: (value: string) => void;
    setSort: (column: string, direction?: 'asc' | 'desc') => void;
    setFilter: (key: string, value: string | string[] | undefined) => void;
    setFilters: (filters: FilterState) => void;
    setPage: (page: number) => void;
    setPerPage: (perPage: number) => void;
    resetFilters: () => void;
};

function getQueryParams(): URLSearchParams {
    if (typeof window === 'undefined') {
        return new URLSearchParams();
    }

    const url = usePage().url;

    try {
        return new URL(url, window.location.origin).searchParams;
    } catch {
        return new URLSearchParams();
    }
}

export function useDataTable({
    only,
    defaultPerPage = 10,
}: UseDataTableOptions): UseDataTableReturn {
    const params = getQueryParams();
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const search = params.get('search') ?? '';
    const page = Number(params.get('page') ?? 1);
    const perPage = Number(params.get('per_page') ?? defaultPerPage);

    const sort: SortState | null = useMemo(() => {
        const column = params.get('sort');
        const direction = params.get('direction') as 'asc' | 'desc' | null;

        if (column && direction) {
            return { column, direction };
        }

        return null;
    }, [params.get('sort'), params.get('direction')]);

    const filters: FilterState = useMemo(() => {
        const result: FilterState = {};

        params.forEach((value, key) => {
            if (
                !['search', 'page', 'per_page', 'sort', 'direction'].includes(
                    key,
                )
            ) {
                result[key] = value;
            }
        });

        return result;
    }, [params.toString()]);

    const reload = useCallback(
        (data: Record<string, unknown>) => {
            // Empty values are kept as `undefined` (not dropped) so Inertia removes
            // them from the current query string instead of keeping the old value.
            const cleanData: Record<string, unknown> = {};

            for (const [key, value] of Object.entries(data)) {
                cleanData[key] =
                    value === null || value === '' ? undefined : value;
            }

            router.reload({
                only,
                data: cleanData as ReloadData,
                replace: true,
            });
        },
        [only],
    );

    const buildData = useCallback(
        (overrides: Record<string, unknown>) => {
            return {
                search: search || undefined,
                page,
                per_page: perPage,
                sort: sort?.column,
                direction: sort?.direction,
                ...filters,
                ...overrides,
            };
        },
        [search, page, perPage, sort, filters],
    );

    const setSearch = useCallback(
        (value: string) => {
            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
            }

            debounceRef.current = setTimeout(() => {
                reload(buildData({ search: value || undefined, page: 1 }));
            }, 300);
        },
        [reload, buildData],
    );

    const setSort = useCallback(
        (column: string, direction?: 'asc' | 'desc') => {
            const newDirection =
                direction ??
                (sort?.column === column && sort?.direction === 'asc'
                    ? 'desc'
                    : 'asc');

            reload(
                buildData({
                    sort: column,
                    direction: newDirection,
                    page: 1,
                }),
            );
        },
        [reload, buildData, sort],
    );

    const setFilter = useCallback(
        (key: string, value: string | string[] | undefined) => {
            reload(buildData({ [key]: value, page: 1 }));
        },
        [reload, buildData],
    );

    const setFilters = useCallback(
        (newFilters: FilterState) => {
            reload(buildData({ ...newFilters, page: 1 }));
        },
        [reload, buildData],
    );

    const setPage = useCallback(
        (newPage: number) => {
            reload(buildData({ page: newPage }));
        },
        [reload, buildData],
    );

    const setPerPage = useCallback(
        (newPerPage: number) => {
            reload(buildData({ per_page: newPerPage, page: 1 }));
        },
        [reload, buildData],
    );

    const resetFilters = useCallback(() => {
        reload({ page: 1, per_page: perPage });
    }, [reload, perPage]);

    return {
        search,
        sort,
        filters,
        page,
        perPage,
        setSearch,
        setSort,
        setFilter,
        setFilters,
        setPage,
        setPerPage,
        resetFilters,
    };
}
