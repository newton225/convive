import { useId } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { NonceStyle } from '@/components/nonce-style';
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
                                <OccupancyFill
                                    ratio={Math.min(
                                        100,
                                        (table.seated / table.capacity) * 100,
                                    )}
                                    full={table.seated >= table.capacity}
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

function OccupancyFill({ ratio, full }: { ratio: number; full: boolean }) {
    // Largeur calculee a l'execution : posee via une balise <style> nonce'e et scopee, pas
    // l'attribut `style` (voir NonceStyle).
    const scopeClass = `occupancy-fill-${useId().replace(/:/g, '')}`;

    return (
        <div
            className={`${scopeClass} h-full ${full ? 'bg-foreground' : 'bg-primary'}`}
        >
            <NonceStyle
                selector={`.${scopeClass}`}
                declarations={{ width: `${ratio}%` }}
            />
        </div>
    );
}
