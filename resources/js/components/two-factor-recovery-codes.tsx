import { router } from '@inertiajs/react';
import { LockKeyhole, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { regenerateRecoveryCodes } from '@/routes/two-factor';

type Props = {
    // Les codes tout juste crees, envoyes une seule fois par le serveur ; null ensuite.
    freshCodes: string[] | null;
    remaining: number;
};

/**
 * Codes de secours de la double authentification (SECURITY.md M5). Le serveur n'en garde que
 * l'empreinte : ils s'affichent une seule fois, a leur creation. Ensuite l'ecran dit seulement
 * combien il en reste, et propose d'en creer de nouveaux.
 */
export default function TwoFactorRecoveryCodes({
    freshCodes,
    remaining,
}: Props) {
    const { t } = useTranslation();
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);

    const regenerate = () => {
        router.post(
            regenerateRecoveryCodes().url,
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
        <Card data-test="recovery-codes">
            <CardHeader>
                <CardTitle className="flex gap-3">
                    <LockKeyhole className="size-4" aria-hidden="true" />
                    {t('account.recovery_codes.title')}
                </CardTitle>
                <CardDescription>
                    {t('account.recovery_codes.description')}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {freshCodes && freshCodes.length > 0 ? (
                    <div className="space-y-3" data-test="recovery-codes-fresh">
                        <p className="text-sm font-medium">
                            {t('account.recovery_codes.shown_once')}
                        </p>
                        <div
                            className="bg-muted grid gap-1 rounded-lg p-4 font-mono text-sm"
                            role="list"
                            aria-label={t('account.recovery_codes.list_label')}
                        >
                            {freshCodes.map((code) => (
                                <div
                                    key={code}
                                    role="listitem"
                                    className="select-text"
                                >
                                    {code}
                                </div>
                            ))}
                        </div>
                    </div>
                ) : (
                    <div
                        className="space-y-1 text-sm"
                        data-test="recovery-codes-remaining"
                    >
                        <p className="font-medium">
                            {t('account.recovery_codes.remaining', {
                                count: remaining,
                            })}
                        </p>
                        <p className="text-muted-foreground">
                            {t('account.recovery_codes.hidden_hint')}
                        </p>
                    </div>
                )}

                <p className="text-muted-foreground text-xs">
                    {t('account.recovery_codes.usage_warning')}
                </p>

                <Button
                    variant="secondary"
                    onClick={() => setConfirming(true)}
                    data-test="recovery-codes-regenerate"
                >
                    <RefreshCw aria-hidden="true" />
                    {t('account.recovery_codes.regenerate')}
                </Button>
            </CardContent>

            <ConfirmActionDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={t('account.recovery_codes.regenerate_title')}
                description={t('account.recovery_codes.regenerate_body')}
                confirmLabel={t('account.recovery_codes.regenerate')}
                onConfirm={regenerate}
                processing={processing}
                testId="recovery-codes-regenerate-confirm"
            />
        </Card>
    );
}
