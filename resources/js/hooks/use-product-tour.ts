import { router, usePage } from '@inertiajs/react';
import { driver } from 'driver.js';
import type { DriveStep } from 'driver.js';
import { useCallback, useEffect, useRef } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { productTours } from '@/lib/product-tours';
import type { ProductTourId } from '@/lib/product-tours';
import { complete } from '@/routes/product-tours';

const isVisible = (element: Element | null): element is HTMLElement =>
    element instanceof HTMLElement && element.getClientRects().length > 0;

/**
 * Une visite guidee du back-office (driver.js). Elle demarre d'elle-meme la premiere fois que le
 * membre ouvre la page, puis seulement a la demande : fermer ou terminer la visite l'enregistre
 * cote serveur, pour tous ses appareils.
 */
export function useProductTour(tour: ProductTourId, autoStart: boolean) {
    const { completedTours } = usePage().props;
    const { t } = useTranslation();
    const started = useRef(false);

    const start = useCallback(() => {
        const steps: DriveStep[] = productTours[tour].flatMap((step) => {
            const popover = {
                title: t(`tours.${tour}.${step.key}.title`),
                description: t(`tours.${tour}.${step.key}.body`),
                side: step.side,
            };

            if (!step.anchor) {
                return [{ popover }];
            }

            const element = document.querySelector(
                `[data-tour="${step.anchor}"]`,
            );

            return isVisible(element) ? [{ element, popover }] : [];
        });

        const tourDriver = driver({
            steps,
            showProgress: true,
            progressText: t('tours.progress'),
            nextBtnText: t('tours.next'),
            prevBtnText: t('tours.previous'),
            doneBtnText: t('tours.done'),
            popoverClass: 'convive-tour',
            animate: !window.matchMedia('(prefers-reduced-motion: reduce)')
                .matches,
            onDestroyed: () => {
                if (completedTours.includes(tour)) {
                    return;
                }

                router.post(
                    complete(tour).url,
                    {},
                    {
                        preserveScroll: true,
                        preserveState: true,
                        only: ['completedTours'],
                    },
                );
            },
        });

        tourDriver.drive();
    }, [tour, t, completedTours]);

    useEffect(() => {
        if (!autoStart || started.current || completedTours.includes(tour)) {
            return;
        }

        started.current = true;
        // Laisse la page se peindre : les reperes doivent exister et avoir leur taille reelle.
        const frame = window.requestAnimationFrame(start);

        return () => window.cancelAnimationFrame(frame);
    }, [autoStart, completedTours, start, tour]);

    return { start };
}
