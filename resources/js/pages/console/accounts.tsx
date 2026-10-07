import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import { AccountList } from '@/components/console/account-list';
import { ListPagination } from '@/components/list-pagination';
import type { PaginationMeta } from '@/components/list-pagination';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { translate, useTranslation } from '@/hooks/use-translation';
import { index } from '@/routes/console/accounts';
import type { ConsoleAccount, Translations } from '@/types';

type Props = {
    search: string;
    minimumSearchLength: number;
    // La page de resultats affichee ; `meta` est nul tant qu'aucune recherche n'est faite.
    results: ConsoleAccount[];
    meta: PaginationMeta | null;
    blocked: ConsoleAccount[];
};

/**
 * Les comptes vus par l'equipe Convive (README section 3). Aucune liste complete : l'ecran montre
 * ce qu'une recherche precise ramene, et les comptes actuellement bloques. La recherche se fait
 * cote serveur.
 */
export default function ConsoleAccounts({
    search,
    minimumSearchLength,
    results,
    meta,
    blocked,
}: Props) {
    const { t } = useTranslation();
    const [term, setTerm] = useState(search);
    const searched = search.length >= minimumSearchLength;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        router.get(
            index().url,
            { q: term.trim() },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={t('console.accounts.title')} />

            <div className="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title={t('console.accounts.title')}
                    description={t('console.accounts.description')}
                />

                <form
                    onSubmit={submit}
                    className="flex flex-wrap items-center gap-2"
                    role="search"
                >
                    <div className="relative min-w-56 flex-1 sm:max-w-md">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                        <Input
                            type="search"
                            name="q"
                            value={term}
                            onChange={(event) => setTerm(event.target.value)}
                            placeholder={t(
                                'console.accounts.search_placeholder',
                            )}
                            aria-label={t('console.accounts.search')}
                            className="pl-8"
                            data-test="console-accounts-search"
                        />
                    </div>
                    <Button
                        type="submit"
                        disabled={term.trim().length < minimumSearchLength}
                    >
                        {t('console.accounts.search')}
                    </Button>
                </form>

                {searched ? (
                    results.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            {t('console.accounts.no_result', { search })}
                        </p>
                    ) : (
                        <>
                            <AccountList accounts={results} />
                            {meta ? (
                                <ListPagination
                                    meta={meta}
                                    onPageChange={(page) =>
                                        router.get(
                                            index().url,
                                            { q: search, page },
                                            {
                                                preserveState: true,
                                                preserveScroll: true,
                                            },
                                        )
                                    }
                                />
                            ) : null}
                        </>
                    )
                ) : (
                    <p className="text-muted-foreground text-sm">
                        {t('console.accounts.search_hint', {
                            count: minimumSearchLength,
                        })}
                    </p>
                )}

                <Card data-test="console-blocked-accounts">
                    <CardHeader>
                        <CardTitle>{t('console.accounts.blocked')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {blocked.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                {t('console.accounts.blocked_empty')}
                            </p>
                        ) : (
                            <AccountList accounts={blocked} />
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ConsoleAccounts.layout = (props: { translations: Translations }) => ({
    wide: true,
    breadcrumbs: [
        {
            title: translate(props.translations, 'console.accounts.title'),
            href: index(),
        },
    ],
});
