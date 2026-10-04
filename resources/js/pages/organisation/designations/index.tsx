import { Head } from '@inertiajs/react';
import { NamedRecordPage } from '@/components/organisation/named-record-page';
import type { NamedRecord } from '@/types';
import {
    destroy,
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/Organisation/DesignationController';
import { dashboard } from '@/routes';

type DesignationsPageProps = {
    designations: NamedRecord[];
};

export default function Designations({ designations }: DesignationsPageProps) {
    return (
        <>
            <Head title="Designations" />
            <NamedRecordPage
                noun="Designation"
                title="Designations"
                description="Job titles shown on employee profiles and payslips."
                records={designations}
                storeUrl={store().url}
                updateUrl={(record) => update(record).url}
                destroyUrl={(record) => destroy(record).url}
            />
        </>
    );
}

Designations.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Designations', href: index().url },
    ],
};
