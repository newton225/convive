import { router } from '@inertiajs/react';
import { LifeBuoy } from 'lucide-react';
import { useState } from 'react';
import { CheckboxRow } from '@/components/settings/checkbox-row';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import { update as updateAvailability } from '@/routes/console/support-availability';
import type { ConsoleSupportGrant } from '@/types';

type Props = {
    // Vrai quand le compte connecte apparait dans la liste proposee aux organisations.
    available: boolean;
    grants: ConsoleSupportGrant[];
};

/**
 * L'acces du support vu par une personne de l'equipe Convive (README section 3) : elle choisit
 * d'apparaitre ou non dans la liste proposee aux organisations, et retrouve les acces qui lui sont
 * ouverts, sa seule porte vers le contenu d'une organisation. Le lien quitte la console pour le
 * back-office de l'organisation : un chargement complet, pas une visite Inertia dans la mise en
 * page de la console.
 */
export function SupportGrantsCard({ available, grants }: Props) {
    const { t, locale } = useTranslation();
    const [saving, setSaving] = useState(false);

    const setAvailable = (next: boolean) => {
        router.put(
            updateAvailability().url,
            { available: next },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );
    };

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
            <CardContent className="space-y-4">
                <CheckboxRow
                    id="support-available"
                    label={t('console.support_grants.availability')}
                    hint={t('console.support_grants.availability_hint')}
                    checked={available}
                    disabled={saving}
                    onChange={setAvailable}
                />

                {grants.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        {t('console.support_grants.empty')}
                    </p>
                ) : (
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
                )}
            </CardContent>
        </Card>
    );
}
