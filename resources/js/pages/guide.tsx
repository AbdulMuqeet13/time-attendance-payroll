import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BookOpen, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import AppLogo from '@/components/app-logo';
import type { Audience } from '@/components/guide/guide-sections';
import { guideSections } from '@/components/guide/guide-sections';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';

const audienceVariant: Record<Audience, 'default' | 'secondary' | 'outline'> = {
    Everyone: 'secondary',
    Admin: 'default',
    HR: 'outline',
    Payroll: 'outline',
    'Branch manager': 'outline',
    'Device admin': 'outline',
    Employee: 'outline',
};

/**
 * Standalone user guide (no app layout), opened in a new tab from the sidebar.
 */
export default function Guide() {
    const [query, setQuery] = useState('');

    const visibleSections = useMemo(() => {
        const words = query.toLowerCase().split(/\s+/).filter(Boolean);

        if (words.length === 0) {
            return guideSections;
        }

        return guideSections.filter((section) => {
            const haystack =
                `${section.title} ${section.keywords} ${section.audience.join(' ')}`.toLowerCase();

            return words.every((word) => haystack.includes(word));
        });
    }, [query]);

    return (
        <>
            <Head title="User Guide" />

            <div className="min-h-screen bg-background">
                <header className="sticky top-0 z-20 border-b bg-background/95 backdrop-blur">
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                        <div className="flex min-w-0 items-center gap-2">
                            <AppLogo />
                        </div>
                        <div className="flex items-center gap-3">
                            <span className="hidden items-center gap-1.5 text-sm font-medium sm:flex">
                                <BookOpen className="size-4" />
                                User Guide
                            </span>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={dashboard()}>
                                    <ArrowLeft className="size-4" />
                                    Back to app
                                </Link>
                            </Button>
                        </div>
                    </div>
                </header>

                <div className="mx-auto grid max-w-6xl gap-8 px-4 py-8 sm:px-6 lg:grid-cols-[240px_1fr]">
                    <aside className="lg:sticky lg:top-20 lg:h-[calc(100vh-6rem)] lg:overflow-y-auto">
                        <div className="relative mb-4">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                className="pl-9"
                                placeholder="Search the guide…"
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                aria-label="Search the guide"
                            />
                        </div>
                        <nav aria-label="Guide contents">
                            <ul className="space-y-0.5 text-sm">
                                {visibleSections.map((section) => (
                                    <li key={section.id}>
                                        <a
                                            href={`#${section.id}`}
                                            className="flex items-center gap-2 rounded-md px-2 py-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                        >
                                            <section.icon className="size-4 shrink-0" />
                                            {section.title}
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </nav>
                    </aside>

                    <main className="min-w-0 space-y-10">
                        <div className="space-y-2">
                            <h1 className="text-3xl font-semibold tracking-tight">
                                User Guide
                            </h1>
                            <p className="text-muted-foreground">
                                How every part of the app works: employees,
                                shifts, devices and fingerprints, attendance,
                                leave, payroll and backups. Each section shows
                                who can use it; you only see the pages your role
                                allows.
                            </p>
                        </div>

                        {visibleSections.length === 0 && (
                            <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                Nothing in the guide matches “{query}”.
                            </p>
                        )}

                        {visibleSections.map((section) => (
                            <section
                                key={section.id}
                                id={section.id}
                                className="scroll-mt-24 space-y-4 border-b pb-10 last:border-b-0"
                            >
                                <div className="flex flex-wrap items-center gap-3">
                                    <span className="flex size-9 items-center justify-center rounded-lg bg-muted">
                                        <section.icon className="size-5" />
                                    </span>
                                    <h2 className="text-xl font-semibold">
                                        {section.title}
                                    </h2>
                                    <div className="flex flex-wrap gap-1.5">
                                        {section.audience.map((audience) => (
                                            <Badge
                                                key={audience}
                                                variant={
                                                    audienceVariant[audience]
                                                }
                                            >
                                                {audience}
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                                <div className="space-y-4 text-sm leading-relaxed">
                                    {section.content}
                                </div>
                            </section>
                        ))}
                    </main>
                </div>
            </div>
        </>
    );
}
