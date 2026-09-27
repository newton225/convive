import { useId } from 'react';
import { NonceStyle } from '@/components/nonce-style';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    occupied: number;
    capacity: number;
};

/**
 * Le remplissage d'un evenement : places occupees sur la capacite, en barre et en chiffres (la
 * barre seule ne suffit pas a porter l'information).
 */
export function EventFillBar({ occupied, capacity }: Props) {
    const { t } = useTranslation();
    // Largeur calculee a l'execution : posee via une balise <style> nonce'e et scopee, pas
    // l'attribut `style` (voir NonceStyle).
    const scopeClass = `event-fill-${useId().replace(/[^a-zA-Z0-9_-]/g, '')}`;
    const ratio = capacity > 0 ? Math.min(1, occupied / capacity) : 0;

    return (
        <div className="space-y-1.5">
            <div className="flex items-baseline justify-between text-xs">
                <span className="text-muted-foreground">
                    {t('events.card.fill_label')}
                </span>
                <span className="font-medium tabular-nums">
                    {t('events.card.fill_value', {
                        occupied,
                        capacity,
                    })}
                </span>
            </div>
            <div className="bg-muted h-1.5 overflow-hidden rounded-full">
                <div
                    className={`${scopeClass} h-full rounded-full ${ratio >= 1 ? 'bg-foreground' : 'bg-primary'}`}
                >
                    <NonceStyle
                        selector={`.${scopeClass}`}
                        declarations={{ width: `${Math.round(ratio * 100)}%` }}
                    />
                </div>
            </div>
        </div>
    );
}
