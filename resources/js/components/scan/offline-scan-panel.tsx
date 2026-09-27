import { CloudOff, RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';

export type LocalScanKind =
    | 'verified'
    | 'already_local'
    | 'forged'
    | 'wrong_event'
    | 'outdated'
    | 'expired'
    | 'revoked'
    | 'unsupported'
    | 'no_key';

type Props = {
    online: boolean;
    queued: number;
    syncing: boolean;
    review: number;
    localResult: LocalScanKind | null;
    onSync: () => void;
    onDismissReview: () => void;
};

const Labels: Record<LocalScanKind, string> = {
    verified: 'offline.scan.verified_offline',
    already_local: 'offline.scan.already_local',
    forged: 'offline.scan.forged',
    wrong_event: 'offline.scan.wrong_event',
    outdated: 'offline.scan.outdated',
    expired: 'offline.scan.expired',
    revoked: 'offline.scan.revoked',
    unsupported: 'offline.scan.unsupported',
    no_key: 'offline.scan.no_key',
};

/**
 * Tout ce que l'agent doit savoir quand le reseau manque : qu'il est hors ligne, le resultat du
 * dernier billet verifie localement, le nombre de scans en attente, et ceux a revoir apres la
 * synchronisation. Un etat ecrit, jamais porte par la seule couleur.
 */
export function OfflineScanPanel({
    online,
    queued,
    syncing,
    review,
    localResult,
    onSync,
    onDismissReview,
}: Props) {
    const { t } = useTranslation();

    if (online && queued === 0 && review === 0 && localResult === null) {
        return null;
    }

    return (
        <Card data-test="scan-offline-panel">
            <CardContent className="space-y-3">
                {!online ? (
                    <div className="flex items-start gap-3" role="status">
                        <CloudOff className="mt-0.5 size-4 shrink-0" />
                        <div className="text-sm">
                            <p className="font-medium">
                                {t('offline.banner.title')}
                            </p>
                            <p className="text-muted-foreground">
                                {t('offline.banner.body')}
                            </p>
                        </div>
                    </div>
                ) : null}

                {localResult ? (
                    <div
                        className="bg-muted rounded-lg p-3 text-sm"
                        role="status"
                        data-test="scan-offline-result"
                        data-kind={localResult}
                    >
                        <p className="font-medium">{t(Labels[localResult])}</p>
                        {localResult === 'verified' ? (
                            <p className="text-muted-foreground">
                                {t('offline.scan.verified_offline_help')}
                            </p>
                        ) : null}
                    </div>
                ) : null}

                {queued > 0 ? (
                    <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span data-test="scan-queued">
                            {t('offline.scan.queued', { count: queued })}
                        </span>
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={!online || syncing}
                            onClick={onSync}
                            data-test="scan-sync"
                        >
                            <RefreshCw
                                className={syncing ? 'animate-spin' : ''}
                            />
                            {syncing
                                ? t('offline.scan.syncing')
                                : t('offline.scan.sync_now')}
                        </Button>
                    </div>
                ) : null}

                {review > 0 ? (
                    <div
                        className="bg-destructive/10 flex items-start justify-between gap-3 rounded-lg p-3 text-sm"
                        role="alert"
                        data-test="scan-review"
                    >
                        <div>
                            <p className="font-medium">
                                {t('offline.scan.review_title')} ({review})
                            </p>
                            <p className="text-muted-foreground">
                                {t('offline.scan.review_item')}
                            </p>
                        </div>
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={onDismissReview}
                        >
                            {t('common.actions.close')}
                        </Button>
                    </div>
                ) : null}
            </CardContent>
        </Card>
    );
}
