import { motion, useReducedMotion } from 'framer-motion';
import { useId } from 'react';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useTranslation } from '@/hooks/use-translation';
import { Duration, EaseOut } from '@/lib/motion';
import type { RefundStatus } from '@/types';

const RefundStatuses: RefundStatus[] = ['due', 'refunded', 'kept'];

type Props = {
    value: RefundStatus;
    onChange: (status: RefundStatus) => void;
};

/**
 * Le choix du sort d'un paiement (README 2.11). Le fond du choix actif glisse d'un bouton a
 * l'autre : le mouvement dit que les trois choix s'excluent et montre ou l'on vient d'aller
 * (CLAUDE.md, « Le mouvement sert la comprehension »). Transform seulement, desactive avec
 * `prefers-reduced-motion`.
 */
export function RefundStatusPicker({ value, onChange }: Props) {
    const { t } = useTranslation();
    const reduceMotion = useReducedMotion() === true;
    // Un identifiant par instance : deux selecteurs a l'ecran ne se partagent pas le repere.
    const indicatorId = useId();

    return (
        <ToggleGroup
            type="single"
            variant="outline"
            value={value}
            onValueChange={(next) => {
                const status = RefundStatuses.find(
                    (candidate) => candidate === next,
                );

                // Un clic sur le choix deja actif le viderait : on garde toujours un choix.
                if (status) {
                    onChange(status);
                }
            }}
            className="w-full"
            aria-label={t('registrations.refund.title')}
        >
            {RefundStatuses.map((status) => (
                <ToggleGroupItem
                    key={status}
                    value={status}
                    className="relative isolate min-h-11 flex-1 transition-colors duration-200 data-[state=on]:bg-transparent"
                    data-test={`refund-status-${status}`}
                >
                    {value === status ? (
                        <motion.span
                            layoutId={indicatorId}
                            aria-hidden
                            className="bg-accent absolute inset-0 -z-10 rounded-[inherit]"
                            transition={
                                reduceMotion
                                    ? { duration: 0 }
                                    : {
                                          duration: Duration.quick,
                                          ease: EaseOut,
                                      }
                            }
                        />
                    ) : null}
                    {t(`registrations.refund.statuses.${status}`)}
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}
