import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { EventCard } from '@/components/events/event-card';
import { Duration, EaseOut } from '@/lib/motion';
import type { EventListItem, TenantPermissions } from '@/types';

type Props = {
    tenantSlug: string;
    events: EventListItem[];
    permissions: TenantPermissions;
    onClose: (event: EventListItem) => void;
};

/**
 * La grille de « Mes evenements » (README ecran 12). Quand le filtre ou la recherche change, les
 * cartes qui sortent s'effacent, celles qui restent glissent a leur nouvelle place et celles qui
 * arrivent apparaissent : on voit ce qui a change, au lieu d'une grille qui saute.
 */
export function EventGrid({ tenantSlug, events, permissions, onClose }: Props) {
    const reduceMotion = useReducedMotion() === true;
    const transition = reduceMotion
        ? { duration: 0 }
        : { duration: Duration.quick, ease: EaseOut };

    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <AnimatePresence mode="popLayout" initial={false}>
                {events.map((event) => (
                    <motion.div
                        key={event.id}
                        layout={!reduceMotion}
                        initial={{ opacity: 0, scale: 0.97 }}
                        animate={{ opacity: 1, scale: 1 }}
                        exit={{ opacity: 0, scale: 0.97 }}
                        transition={transition}
                        className="grid min-w-0"
                    >
                        <EventCard
                            tenantSlug={tenantSlug}
                            event={event}
                            permissions={permissions}
                            onClose={() => onClose(event)}
                        />
                    </motion.div>
                ))}
            </AnimatePresence>
        </div>
    );
}
