import { HelpTip } from '@/components/help-tip';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import type { DashboardActivity } from '@/types';

type Props = {
    activity: DashboardActivity[];
};

/**
 * L'activite recente : une phrase par evenement, composee a partir du type et du nom, dans la
 * langue courante.
 */
export function RecentActivity({ activity }: Props) {
    const { t, locale } = useTranslation();

    return (
        <Card data-test="dashboard-activity">
            <CardHeader>
                <CardTitle className="flex items-center gap-1.5 text-base">
                    {t('dashboard.activity.title')}
                    <HelpTip subject={t('dashboard.activity.title')}>
                        {t('dashboard.help.activity')}
                    </HelpTip>
                </CardTitle>
            </CardHeader>
            <CardContent>
                {activity.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        {t('dashboard.activity.empty')}
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {activity.map((item) => (
                            <li key={item.id} className="text-sm">
                                <p>
                                    {t(
                                        `dashboard.activity.types.${item.type}`,
                                        {
                                            name: item.name ?? '',
                                        },
                                    )}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    {formatDateTime(item.at, locale)}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
