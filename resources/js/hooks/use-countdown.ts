import { useEffect, useState } from 'react';

/**
 * Decompte de reservation (README ecran 5) : element propre au metier, l'un des rares composants
 * maison autorises par CLAUDE.md. Recalcule chaque seconde a partir de l'heure d'expiration
 * envoyee par le serveur, jamais d'une duree comptee cote client, pour rester juste meme si
 * l'onglet reste ouvert longtemps avant que l'invite ne revienne dessus.
 */
export function useCountdown(targetIso: string | null): {
    remainingSeconds: number;
    hasExpired: boolean;
} {
    const target = targetIso ? new Date(targetIso).getTime() : null;
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        if (target === null) {
            return;
        }

        const interval = setInterval(() => setNow(Date.now()), 1000);

        return () => clearInterval(interval);
    }, [target]);

    // Pas d'echeance a suivre (statut autre que « held », voir l'appelant) : ce n'est pas la
    // meme chose qu'une echeance depassee. Un appelant qui ne passe pas de date ne doit jamais
    // se retrouver traite comme expire.
    if (target === null) {
        return { remainingSeconds: 0, hasExpired: false };
    }

    const remainingSeconds = Math.max(0, Math.round((target - now) / 1000));

    return { remainingSeconds, hasExpired: remainingSeconds <= 0 };
}

export function formatCountdown(remainingSeconds: number): string {
    const minutes = Math.floor(remainingSeconds / 60);
    const seconds = remainingSeconds % 60;

    return `${minutes}:${String(seconds).padStart(2, '0')}`;
}
