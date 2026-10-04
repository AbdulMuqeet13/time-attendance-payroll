import { Head } from '@inertiajs/react';
import { NamedRecordPage } from '@/components/organisation/named-record-page';
import type { NamedRecord } from '@/types';
import {
    destroy,
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/Organisation/DepartmentController';
import { dashboard } from '@/routes';

type DepartmentsPageProps = {
    departments: NamedRecord[];
};

export default function Departments({ departments }: DepartmentsPageProps) {
    return (
        <>
            <Head title="Departments" />
            <NamedRecordPage
                noun="Department"
                title="Departments"
                description="Group employees by department for filters and reports."
                records={departments}
                storeUrl={store().url}
                updateUrl={(record) => update(record).url}
                destroyUrl={(record) => destroy(record).url}
            />
        </>
    );
}

Departments.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Departments', href: index().url },
    ],
};
