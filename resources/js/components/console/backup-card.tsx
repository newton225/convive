import { TriangleAlert } from 'lucide-react';
import { RunBackupButton } from '@/components/console/run-backup-button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import { formatMegabytes } from '@/lib/format-size';
import type { ConsoleBackup } from '@/types';

type Props = {
    backup: ConsoleBackup;
};

/**
 * L'etat des sauvegardes sur l'ecran de sante technique (README ecran 31) : ce qui existe
 * reellement sur la destination, et ce qui manque encore pour qu'elle protege vraiment (stockage
 * exterieur, chiffrement).
 */
export function BackupCard({ backup }: Props) {
    const { t, locale } = useTranslation();

    const warnings = [
        !backup.healthy && backup.lastAt !== null
            ? t('console.health.backup_unhealthy')
            : null,
        backup.onApplicationServer
            ? t('console.health.backup_same_server')
            : null,
        backup.encrypted ? null : t('console.health.backup_not_encrypted'),
    ].filter((warning) => warning !== null);

    return (
        <Card data-test="console-backup">
            <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
                <CardTitle>{t('console.health.backup')}</CardTitle>
                <RunBackupButton />
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
                <p className="text-muted-foreground">
                    {t('console.health.backup_help')}
                </p>

                {backup.lastAt === null ? (
                    <p>{t('console.health.backup_none')}</p>
                ) : (
                    <div className="flex flex-wrap items-center gap-3">
                        <Badge
                            variant={
                                backup.healthy ? 'secondary' : 'destructive'
                            }
                        >
                            {backup.healthy
                                ? t('console.health.healthy')
                                : t('console.health.unhealthy')}
                        </Badge>
                        <span>
                            {t('console.health.backup_last', {
                                date: formatDateTime(backup.lastAt, locale),
                            })}
                        </span>
                        <span className="text-muted-foreground">
                            {t('console.health.backup_size', {
                                size: formatMegabytes(
                                    backup.lastSizeBytes,
                                    locale,
                                ),
                            })}
                        </span>
                        <span className="text-muted-foreground">
                            {t('console.health.backup_kept', {
                                count: backup.count,
                                size: formatMegabytes(
                                    backup.totalSizeBytes,
                                    locale,
                                ),
                            })}
                        </span>
                    </div>
                )}

                {warnings.length > 0 ? (
                    <ul className="space-y-2">
                        {warnings.map((warning) => (
                            <li key={warning} className="flex gap-2">
                                <TriangleAlert
                                    className="mt-0.5 size-4 shrink-0"
                                    aria-hidden
                                />
                                <span>{warning}</span>
                            </li>
                        ))}
                    </ul>
                ) : null}
            </CardContent>
        </Card>
    );
}
