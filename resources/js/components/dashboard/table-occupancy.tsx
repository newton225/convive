import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import type { DashboardTableOccupancy } from '@/types';

type Props = {
    tables: DashboardTableOccupancy[];
};

/**
 * L'occupation des tables : chaque table ecrit ses places prises, la jauge n'est qu'un renfort.
 */
export function TableOccupancy({ tables }: Props) {
    const { t } = useTranslation();

    return (
        <Card data-test="dashboard-table-occupancy">
            <CardHeader>
                <CardTitle className="text-base">
                    {t('dashboard.charts.tables')}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <ul className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {tables.map((table) => (
                        <li
                            key={table.number}
                            className="bg-muted/60 space-y-2 rounded-lg p-3"
                        >
                            <p className="text-sm font-medium">
                                {t('dashboard.charts.table_label', {
                                    number: table.number,
                                })}
                            </p>
                            <div
                                className="bg-background h-1.5 overflow-hidden rounded-full"
                                role="progressbar"
                                aria-valuenow={table.seated}
                                aria-valuemin={0}
                                aria-valuemax={table.capacity}
                                aria-label={t('dashboard.charts.table_label', {
                                    number: table.number,
                                })}
                            >
                                <div
                                    className={
                                        table.seated >= table.capacity
                                            ? 'bg-foreground h-full'
                                            : 'bg-primary h-full'
                                    }
                                    style={{
                                        width: `${Math.min(100, (table.seated / table.capacity) * 100)}%`,
                                    }}
                                />
                            </div>
                            <p className="text-muted-foreground text-xs tabular-nums">
                                {t('dashboard.charts.seated', {
                                    seated: table.seated,
                                    capacity: table.capacity,
                                })}
                            </p>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}
