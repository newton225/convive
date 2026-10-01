import { router } from '@inertiajs/react';
import { DatabaseZap } from 'lucide-react';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { migrate } from '@/routes/console/health/databases';

type Props = {
    slug: string;
    name: string;
};

/**
 * « Rejouer les migrations » sur la base d'une organisation signalee par la sante technique
 * (README ecran 31). Le geste ne s'annule pas : la confirmation dit ce qu'il fait et sur quelle
 * organisation.
 */
export function RepairDatabaseButton({ slug, name }: Props) {
    const { t } = useTranslation();
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);

    const repair = () => {
        router.post(
            migrate(slug).url,
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setConfirming(false);
                },
            },
        );
    };

    return (
        <>
            <Button
                variant="outline"
                size="sm"
                onClick={() => setConfirming(true)}
                data-test="console-repair-database"
            >
                <DatabaseZap />
                {t('console.health.migrate')}
            </Button>
            <ConfirmActionDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={t('console.health.migrate_confirm.title', {
                    organisation: name,
                })}
                description={t('console.health.migrate_confirm.description')}
                confirmLabel={t('console.health.migrate')}
                onConfirm={repair}
                processing={processing}
                testId="console-repair-database-confirm"
            />
        </>
    );
}
