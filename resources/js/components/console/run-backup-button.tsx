import { router } from '@inertiajs/react';
import { DatabaseBackup } from 'lucide-react';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { backup } from '@/routes/console/health';

/**
 * « Sauvegarder maintenant » (README ecran 31) : la meme sauvegarde que celle de la nuit, lancee a
 * la demande. La confirmation dit ce qui est cree et que le geste est journalise.
 */
export function RunBackupButton() {
    const { t } = useTranslation();
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);

    const run = () => {
        router.post(
            backup().url,
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
                data-test="console-run-backup"
            >
                <DatabaseBackup />
                {t('console.health.backup_now')}
            </Button>
            <ConfirmActionDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={t('console.health.backup_confirm.title')}
                description={t('console.health.backup_confirm.description')}
                confirmLabel={t('console.health.backup_now')}
                onConfirm={run}
                processing={processing}
                testId="console-run-backup-confirm"
            />
        </>
    );
}
