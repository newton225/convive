import {
    CircleAlert,
    CircleCheck,
    CircleMinus,
    TriangleAlert,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import type { ConsoleHealthCheck } from '@/types';

type Props = {
    checks: ConsoleHealthCheck[];
};

// L'ordre de lecture : ce qui fait tourner le reste d'abord.
const CheckOrder = [
    'schedule',
    'scheduled_tasks',
    'queue',
    'failed_jobs',
    'audit_chain',
    'database',
    'redis',
    'disk',
];

const StatusIcons = {
    ok: CircleCheck,
    warning: TriangleAlert,
    failed: CircleAlert,
    crashed: CircleAlert,
    skipped: CircleMinus,
};

/**
 * Les controles de sante (README ecran 31), un par ligne : ce qui est surveille, son etat, et en
 * clair ce que cet etat veut dire et quoi verifier. L'etat est ecrit, pas seulement colore.
 */
export function HealthChecksCard({ checks }: Props) {
    const { t } = useTranslation();
    const ordered = [...checks].sort(
        (first, second) =>
            CheckOrder.indexOf(first.name) - CheckOrder.indexOf(second.name),
    );

    return (
        <Card data-test="console-health-checks">
            <CardHeader>
                <CardTitle>{t('console.health.checks_title')}</CardTitle>
                <p className="text-muted-foreground text-sm">
                    {t('console.health.checks_hint')}
                </p>
            </CardHeader>
            <CardContent>
                {ordered.length === 0 ? (
                    <p className="text-sm">
                        {t('console.health.checks_empty')}
                    </p>
                ) : (
                    <ul className="divide-y text-sm">
                        {ordered.map((check) => {
                            const Icon = StatusIcons[check.status];
                            const failing =
                                check.status === 'failed' ||
                                check.status === 'crashed';
                            // Un controle non joue ou impossible n'a rien a expliquer de plus
                            // que son etat.
                            const explained =
                                check.status === 'ok' ||
                                check.status === 'warning' ||
                                check.status === 'failed';

                            return (
                                <li
                                    key={check.name}
                                    className="flex flex-wrap items-start gap-x-3 gap-y-1 py-2"
                                    data-test={`console-health-check-${check.name}`}
                                >
                                    <Icon
                                        className="mt-0.5 size-4 shrink-0"
                                        aria-hidden
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span className="font-medium">
                                            {t(
                                                `console.health.checks.${check.name}.label`,
                                            )}
                                        </span>
                                        {explained ? (
                                            <span className="text-muted-foreground block">
                                                {t(
                                                    `console.health.checks.${check.name}.${check.status}`,
                                                )}
                                            </span>
                                        ) : null}
                                    </span>
                                    <Badge
                                        variant={
                                            failing
                                                ? 'destructive'
                                                : check.status === 'ok'
                                                  ? 'secondary'
                                                  : 'outline'
                                        }
                                    >
                                        {t(
                                            `console.health.check_status.${check.status}`,
                                        )}
                                    </Badge>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
