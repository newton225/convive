import { useReducedMotion } from 'framer-motion';
import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import type { DashboardDayCount } from '@/types';

type Props = {
    data: DashboardDayCount[];
};

/**
 * Les inscriptions par jour. Le graphique est decoratif pour un lecteur d'ecran (`aria-hidden`) :
 * la phrase de synthese et la liste des valeurs, cachees a l'ecran, portent la meme information.
 * L'animation de tracé s'arrete avec `prefers-reduced-motion`.
 */
export function RegistrationsChart({ data }: Props) {
    const { t, locale } = useTranslation();
    const reduceMotion = useReducedMotion();
    const total = data.reduce((sum, day) => sum + day.count, 0);

    const shortDate = (value: string) =>
        new Intl.DateTimeFormat(locale, {
            day: 'numeric',
            month: 'short',
        }).format(new Date(value));

    return (
        <Card data-test="dashboard-registrations-chart">
            <CardHeader>
                <CardTitle className="text-base">
                    {t('dashboard.charts.per_day')}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p className="sr-only">
                    {t('dashboard.charts.per_day_summary', {
                        days: data.length,
                        total,
                    })}
                </p>
                <ul className="sr-only">
                    {data.map((day) => (
                        <li key={day.date}>
                            {shortDate(day.date)} : {day.count}
                        </li>
                    ))}
                </ul>
                <div className="h-56" aria-hidden="true">
                    <ResponsiveContainer width="100%" height="100%">
                        <AreaChart
                            data={data}
                            margin={{ top: 8, right: 8, bottom: 0, left: -12 }}
                        >
                            <CartesianGrid
                                vertical={false}
                                stroke="var(--border)"
                                strokeDasharray="3 3"
                            />
                            <XAxis
                                dataKey="date"
                                tickFormatter={shortDate}
                                tick={{
                                    fill: 'var(--muted-foreground)',
                                    fontSize: 12,
                                }}
                                axisLine={false}
                                tickLine={false}
                                minTickGap={24}
                            />
                            <YAxis
                                allowDecimals={false}
                                tick={{
                                    fill: 'var(--muted-foreground)',
                                    fontSize: 12,
                                }}
                                axisLine={false}
                                tickLine={false}
                                width={36}
                            />
                            <Tooltip
                                labelFormatter={(value) =>
                                    typeof value === 'string'
                                        ? shortDate(value)
                                        : ''
                                }
                                formatter={(value) => [
                                    value,
                                    t('dashboard.charts.registrations_series'),
                                ]}
                                contentStyle={{
                                    background: 'var(--popover)',
                                    border: '1px solid var(--border)',
                                    borderRadius: 8,
                                    color: 'var(--popover-foreground)',
                                }}
                            />
                            <Area
                                type="monotone"
                                dataKey="count"
                                stroke="var(--primary)"
                                strokeWidth={2}
                                fill="var(--primary)"
                                fillOpacity={0.15}
                                isAnimationActive={!reduceMotion}
                            />
                        </AreaChart>
                    </ResponsiveContainer>
                </div>
            </CardContent>
        </Card>
    );
}
