import { LifeBuoy } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import type { ConsoleSupportGrant } from '@/types';

type Props = {
    grants: ConsoleSupportGrant[];
};

/**
 * Les acces de support ouverts au compte connecte (README section 3) : sa seule porte vers le
 * contenu d'une organisation. Absente tant qu'aucun Proprietaire ne lui en a ouvert. Le lien quitte
 * la console pour le back-office de l'organisation : un chargement complet, pas une visite Inertia
 * dans la mise en page de la console.
 */
export function SupportGrantsCard({ grants }: Props) {
    const { t, locale } = useTranslation();

    if (grants.length === 0) {
        return null;
    }

    return (
        <Card data-test="console-support-grants">
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <LifeBuoy className="size-4" aria-hidden />
                    {t('console.support_grants.title')}
                </CardTitle>
                <p className="text-muted-foreground text-sm">
                    {t('console.support_grants.description')}
                </p>
            </CardHeader>
            <CardContent>
                <ul className="divide-border divide-y">
                    {grants.map((grant) => (
                        <li
                            key={grant.id}
                            className="flex flex-wrap items-center justify-between gap-3 py-2 text-sm"
                        >
                            <span>
                                <span className="font-medium">
                                    {grant.organisation}
                                </span>
                                <span className="text-muted-foreground ml-2">
                                    {t('console.support_grants.until', {
                                        expires: formatDateTime(
                                            grant.expiresAt,
                                            locale,
                                        ),
                                    })}
                                </span>
                            </span>
                            <Button asChild variant="outline" size="sm">
                                <a
                                    href={grant.url}
                                    data-test="console-support-grant-open"
                                >
                                    {t('console.support_grants.open')}
                                </a>
                            </Button>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}
