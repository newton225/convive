import { useCallback, useEffect, useRef, useState } from 'react';
import {
    readLockState,
    verifyScanPin,
    writeLockState,
    type ScanLockState,
    type ScanPinVerifier,
} from '@/lib/scan-pin';

export const ScanLockTimeoutMs = 5 * 60 * 1000;

export const ScanLockMaxAttempts = 5;

export type UnlockOutcome = 'unlocked' | 'wrong' | 'exhausted';

/**
 * Verrouillage de l'ecran de scan apres 5 minutes sans activite (SECURITY.md M8). Toute interaction
 * avec la page (toucher, clavier) et chaque billet lu repoussent l'echeance. Au-dela de 5 essais
 * rates, l'appelant doit fermer la session : le code ne suffit plus.
 */
export function useScanLock(verifier: ScanPinVerifier | null) {
    const [state, setState] = useState<ScanLockState>(() => {
        const stored = readLockState();

        // Une page rechargee longtemps apres la derniere activite revient verrouillee.
        return Date.now() - stored.lastActivity > ScanLockTimeoutMs
            ? { ...stored, locked: true }
            : stored;
    });
    const stateRef = useRef(state);

    const update = useCallback((next: ScanLockState) => {
        stateRef.current = next;
        writeLockState(next);
        setState(next);
    }, []);

    const registerActivity = useCallback(() => {
        if (!stateRef.current.locked) {
            update({ ...stateRef.current, lastActivity: Date.now() });
        }
    }, [update]);

    useEffect(() => {
        if (verifier === null) {
            return;
        }

        const events = ['pointerdown', 'keydown', 'touchstart'] as const;
        events.forEach((name) =>
            window.addEventListener(name, registerActivity, { passive: true }),
        );

        const interval = window.setInterval(() => {
            const current = stateRef.current;

            if (
                !current.locked &&
                Date.now() - current.lastActivity > ScanLockTimeoutMs
            ) {
                update({ ...current, locked: true });
            }
        }, 15_000);

        return () => {
            events.forEach((name) =>
                window.removeEventListener(name, registerActivity),
            );
            window.clearInterval(interval);
        };
    }, [verifier, registerActivity, update]);

    const unlock = useCallback(
        async (pin: string): Promise<UnlockOutcome> => {
            if (verifier === null) {
                return 'exhausted';
            }

            if (await verifyScanPin(pin, verifier)) {
                update({
                    locked: false,
                    lastActivity: Date.now(),
                    attempts: 0,
                });

                return 'unlocked';
            }

            const attempts = stateRef.current.attempts + 1;
            update({ ...stateRef.current, attempts });

            return attempts >= ScanLockMaxAttempts ? 'exhausted' : 'wrong';
        },
        [verifier, update],
    );

    return {
        locked: verifier !== null && state.locked,
        attemptsLeft: Math.max(0, ScanLockMaxAttempts - state.attempts),
        registerActivity,
        unlock,
    };
}
