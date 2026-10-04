import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * Met une zone en evidence quelques secondes, a l'ouverture si `initially` est vrai, puis a chaque
 * appel de `show()`, qui y fait aussi descendre la page.
 */
export function useTemporaryHighlight<T extends HTMLElement>(
    initially: boolean,
    durationMs = 6000,
) {
    const ref = useRef<T>(null);
    const [active, setActive] = useState(initially);
    const [round, setRound] = useState(0);

    useEffect(() => {
        if (!active) {
            return;
        }

        const timer = window.setTimeout(() => setActive(false), durationMs);

        return () => window.clearTimeout(timer);
    }, [active, round, durationMs]);

    const show = useCallback(() => {
        const reduceMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        ref.current?.scrollIntoView({
            behavior: reduceMotion ? 'auto' : 'smooth',
            block: 'center',
        });
        setActive(true);
        setRound((value) => value + 1);
    }, []);

    return { ref, active, show };
}
