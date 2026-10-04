import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { destroy } from '@/routes/console/health/orphan-databases';

/**
 * « Supprimer » une base qui n'appartient a aucune organisation, signalee par la sante technique
 * (incident du 2026-10-04). Le geste ne s'annule pas : la confirmation nomme le fichier et dit ce
 * qu'il en reste.
 */
export function DeleteOrphanDatabaseButton({ file }: { file: string }) {
    const { t } = useTranslation();
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);

    const remove = () => {
        router.delete(destroy(file).url, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setConfirming(false);
            },
        });
    };

    return (
        <>
            <Button
                variant="outline"
                size="sm"
                onClick={() => setConfirming(true)}
                data-test="console-delete-orphan-database"
            >
                <Trash2 />
                {t('console.health.orphans.delete')}
            </Button>
            <ConfirmActionDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={t('console.health.orphans.confirm.title', { file })}
                description={t('console.health.orphans.confirm.description')}
                confirmLabel={t('console.health.orphans.delete')}
                onConfirm={remove}
                destructive
                processing={processing}
                testId="console-delete-orphan-database-confirm"
            />
        </>
    );
}
