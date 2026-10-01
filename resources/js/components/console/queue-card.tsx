import { router } from '@inertiajs/react';
import { RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import { destroy, retry } from '@/routes/console/health/failed-jobs';
import type { ConsoleQueue } from '@/types';

type Props = {
    queue: ConsoleQueue;
};

type PendingAction = { kind: 'retry' | 'forget'; id: string };

/**
 * La file et ses envois en echec (README ecran 31) : ce qui attend d'etre traite, et ce qui a echoue
 * pour de bon, avec la premiere ligne de l'erreur. Relancer renvoie un message, ecarter le perd :
 * aucun des deux ne s'annule, d'ou la confirmation.
 */
export function QueueCard({ queue }: Props) {
    const { t, locale } = useTranslation();
    const [action, setAction] = useState<PendingAction | null>(null);
    const [processing, setProcessing] = useState(false);

    const confirm = () => {
        if (action === null) {
            return;
        }

        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setAction(null);
            },
        };

        if (action.kind === 'retry') {
            router.post(retry(action.id).url, {}, options);
        } else {
            router.delete(destroy(action.id).url, options);
        }
    };

    return (
        <Card data-test="console-queue">
            <CardHeader>
                <CardTitle>{t('console.health.queue_title')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
                <p>
                    {queue.pending === null
                        ? t('console.health.queue_unreachable')
                        : t('console.health.queue_pending', {
                              count: queue.pending,
                          })}
                </p>

                {queue.failedCount === 0 ? (
                    <p className="text-muted-foreground">
                        {t('console.health.failed_jobs_none')}
                    </p>
                ) : (
                    <>
                        <p className="font-medium">
                            {t('console.health.failed_jobs_count', {
                                count: queue.failedCount,
                            })}
                            {queue.failedCount > queue.failed.length
                                ? ` ${t('console.health.failed_jobs_more', { shown: queue.failed.length })}`
                                : ''}
                        </p>
                        <ul className="divide-y">
                            {queue.failed.map((job) => (
                                <li
                                    key={job.id}
                                    className="flex flex-wrap items-start justify-between gap-3 py-2"
                                >
                                    <span className="min-w-0 flex-1">
                                        <span className="font-medium">
                                            {job.job}
                                        </span>
                                        <span className="text-muted-foreground ml-2">
                                            {t('console.health.failed_at', {
                                                date: formatDateTime(
                                                    job.failedAt,
                                                    locale,
                                                ),
                                            })}
                                        </span>
                                        <span className="text-muted-foreground block text-xs break-words">
                                            {job.error}
                                        </span>
                                    </span>
                                    <span className="flex flex-wrap gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                setAction({
                                                    kind: 'retry',
                                                    id: job.id,
                                                })
                                            }
                                            data-test="console-failed-job-retry"
                                        >
                                            <RotateCcw />
                                            {t('console.health.retry')}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                setAction({
                                                    kind: 'forget',
                                                    id: job.id,
                                                })
                                            }
                                            data-test="console-failed-job-forget"
                                        >
                                            <Trash2 />
                                            {t('console.health.forget')}
                                        </Button>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </>
                )}
            </CardContent>

            <ConfirmActionDialog
                open={action !== null}
                onOpenChange={(open) => !open && setAction(null)}
                title={t(
                    action?.kind === 'forget'
                        ? 'console.health.forget_confirm.title'
                        : 'console.health.retry_confirm.title',
                )}
                description={t(
                    action?.kind === 'forget'
                        ? 'console.health.forget_confirm.description'
                        : 'console.health.retry_confirm.description',
                )}
                confirmLabel={t(
                    action?.kind === 'forget'
                        ? 'console.health.forget'
                        : 'console.health.retry',
                )}
                destructive={action?.kind === 'forget'}
                onConfirm={confirm}
                processing={processing}
                testId="console-failed-job-confirm"
            />
        </Card>
    );
}
