import { useEffect, useState } from 'react';

/**
 * Un compte a rebours de demonstration pour la vitrine : il descend d'une seconde a la fois tant
 * qu'il est actif, et repart de sa valeur de depart une fois a zero. Inactif (hors de l'ecran, ou
 * `prefers-reduced-motion`), il reste fige sur sa valeur de depart. Ce n'est jamais le vrai
 * decompte d'une reservation, qui vit cote serveur.
 */
export function useLoopingCountdown(
    fromSeconds: number,
    active: boolean,
): number {
    const [seconds, setSeconds] = useState(fromSeconds);

    useEffect(() => {
        if (!active) {
            return;
        }

        const interval = window.setInterval(() => {
            setSeconds((current) => (current <= 1 ? fromSeconds : current - 1));
        }, 1000);

        return () => window.clearInterval(interval);
    }, [active, fromSeconds]);

    return active ? seconds : fromSeconds;
}
