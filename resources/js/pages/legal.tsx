import { Head, Link } from '@inertiajs/react';
import { SiteFooter } from '@/components/site/site-footer';
import { SiteHeader } from '@/components/site/site-header';
import { useTranslation } from '@/hooks/use-translation';
import { formatDate } from '@/lib/format-date';
import { notice, privacy, terms } from '@/routes/legal';
import type { LegalDocument, LegalLabels } from '@/types';

type Props = {
    document: LegalDocument;
    // Date de la version en vigueur, celle qu'un compte accepte a sa creation.
    version: string;
    labels: LegalLabels;
};

/**
 * Une page juridique du site : politique de confidentialite, conditions d'utilisation ou mentions
 * legales. Le texte arrive deja traduit et complete par le serveur (`App\Support\LegalDocument`) ;
 * la page ne fait que le mettre en forme, avec un sommaire et les liens vers les deux autres
 * documents.
 */
export default function Legal({ document, version, labels }: Props) {
    const { locale } = useTranslation();

    const documents = [
        { slug: 'privacy', href: privacy(), label: labels.nav.privacy },
        { slug: 'terms', href: terms(), label: labels.nav.terms },
        { slug: 'notice', href: notice(), label: labels.nav.notice },
    ];

    return (
        <div className="bg-background text-foreground min-h-screen">
            <Head title={document.title} />

            <SiteHeader />

            <main className="mx-auto w-full max-w-3xl px-4 py-12 sm:px-6 sm:py-16">
                <nav aria-label={labels.nav.title} className="mb-8">
                    <ul className="flex flex-wrap gap-x-5 gap-y-2 text-sm">
                        {documents.map((item) => (
                            <li key={item.slug}>
                                <Link
                                    href={item.href}
                                    aria-current={
                                        item.slug === document.slug
                                            ? 'page'
                                            : undefined
                                    }
                                    className={
                                        item.slug === document.slug
                                            ? 'font-semibold underline underline-offset-4'
                                            : 'text-muted-foreground hover:text-foreground transition-colors'
                                    }
                                >
                                    {item.label}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </nav>

                <header className="space-y-3">
                    <h1 className="text-3xl font-semibold tracking-tight">
                        {document.title}
                    </h1>
                    <p className="text-muted-foreground">{document.summary}</p>
                    <p className="text-muted-foreground text-sm">
                        {labels.updated.replace(
                            ':date',
                            formatDate(version, locale),
                        )}
                    </p>
                </header>

                <nav
                    aria-label={labels.contents}
                    className="bg-card mt-8 rounded-xl p-5"
                >
                    <h2 className="text-sm font-semibold">{labels.contents}</h2>
                    <ol className="mt-3 list-decimal space-y-1 pl-5 text-sm">
                        {document.sections.map((section) => (
                            <li key={section.id}>
                                <a
                                    href={`#${section.id}`}
                                    className="hover:underline"
                                >
                                    {section.title}
                                </a>
                            </li>
                        ))}
                    </ol>
                </nav>

                <div className="mt-10 space-y-10">
                    {document.sections.map((section, index) => (
                        <section
                            key={section.id}
                            id={section.id}
                            className="scroll-mt-24 space-y-3"
                        >
                            <h2 className="text-xl font-semibold">
                                {index + 1}. {section.title}
                            </h2>
                            {section.paragraphs.map((paragraph) => (
                                <p key={paragraph} className="leading-relaxed">
                                    {paragraph}
                                </p>
                            ))}
                            {section.items.length > 0 ? (
                                <ul className="list-disc space-y-2 pl-5 leading-relaxed">
                                    {section.items.map((item) => (
                                        <li key={item}>{item}</li>
                                    ))}
                                </ul>
                            ) : null}
                            {section.after.map((paragraph) => (
                                <p key={paragraph} className="leading-relaxed">
                                    {paragraph}
                                </p>
                            ))}
                        </section>
                    ))}
                </div>
            </main>

            <SiteFooter />
        </div>
    );
}
