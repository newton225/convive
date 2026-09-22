import { useReducedMotion } from 'framer-motion';
import {
    Bar,
    BarChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import type { DashboardChannelCount } from '@/types';

type Props = {
    data: DashboardChannelCount[];
};

/**
 * Les preuves par canal de paiement. Meme principe d'accessibilite que le graphique des
 * inscriptions : le dessin est masque aux lecteurs d'ecran, une liste ecrite porte les valeurs.
 */
export function ChannelsChart({ data }: Props) {
    const { t } = useTranslation();
    const reduceMotion = useReducedMotion();

    return (
        <Card data-test="dashboard-channels-chart">
            <CardHeader>
                <CardTitle className="text-base">
                    {t('dashboard.charts.by_channel')}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p className="sr-only">
                    {t('dashboard.charts.by_channel_summary')}
                </p>
                <ul className="sr-only">
                    {data.map((item) => (
                        <li key={item.channel}>
                            {item.channel} : {item.count}
                        </li>
                    ))}
                </ul>
                <div className="h-56" aria-hidden="true">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart
                            data={data}
                            layout="vertical"
                            margin={{ top: 4, right: 16, bottom: 0, left: 8 }}
                        >
                            <XAxis type="number" hide allowDecimals={false} />
                            <YAxis
                                type="category"
                                dataKey="channel"
                                width={96}
                                tick={{
                                    fill: 'var(--foreground)',
                                    fontSize: 13,
                                }}
                                axisLine={false}
                                tickLine={false}
                            />
                            <Tooltip
                                cursor={{ fill: 'var(--muted)' }}
                                formatter={(value) => [
                                    value,
                                    t('dashboard.charts.proofs_series'),
                                ]}
                                contentStyle={{
                                    background: 'var(--popover)',
                                    border: '1px solid var(--border)',
                                    borderRadius: 8,
                                    color: 'var(--popover-foreground)',
                                }}
                            />
                            <Bar
                                dataKey="count"
                                fill="var(--primary)"
                                radius={[0, 4, 4, 0]}
                                isAnimationActive={!reduceMotion}
                                label={{
                                    position: 'right',
                                    fill: 'var(--muted-foreground)',
                                    fontSize: 12,
                                }}
                            />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            </CardContent>
        </Card>
    );
}
