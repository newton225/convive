import { useTranslation } from '@/hooks/use-translation';

const Tables = [
    { number: 1, seated: 8 },
    { number: 2, seated: 10 },
    { number: 3, seated: 5 },
    { number: 4, seated: 10 },
    { number: 5, seated: 3 },
    { number: 6, seated: 7 },
] as const;

/**
 * Le plan de salle (README ecran 21) : chaque table montre ses places prises. Le nombre est ecrit,
 * la jauge n'est qu'un renfort visuel.
 */
export function SeatingPreview() {
    const { t } = useTranslation();

    return (
        <div data-test="site-seating-preview" className="space-y-2">
            <p className="text-muted-foreground text-sm">
                {t('site.preview.seating.title')}
            </p>
            <ul className="grid grid-cols-3 gap-2">
                {Tables.map((table) => (
                    <li
                        key={table.number}
                        className="bg-muted/60 space-y-1.5 rounded-lg p-2.5"
                    >
                        <div className="flex items-baseline justify-between text-xs">
                            <span className="font-medium">
                                {t('site.preview.seating.table', {
                                    number: table.number,
                                })}
                            </span>
                            <span className="text-muted-foreground tabular-nums">
                                {table.seated}/10
                            </span>
                        </div>
                        <div className="bg-background h-1.5 overflow-hidden rounded-full">
                            <div
                                className={
                                    table.seated === 10
                                        ? 'bg-foreground h-full'
                                        : 'bg-primary h-full'
                                }
                                style={{ width: `${table.seated * 10}%` }}
                            />
                        </div>
                    </li>
                ))}
            </ul>
        </div>
    );
}
