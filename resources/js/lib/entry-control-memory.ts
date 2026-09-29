/**
 * L'evenement que ce telephone controle aujourd'hui (README ecran 26). Deux evenements le meme
 * jour, en deux lieux : l'hotesse de Bouake qui rouvre le raccourci « Controle a l'entree » doit
 * retomber sur son scan sans refaire le choix. Le souvenir ne vaut que pour la journee, et seulement
 * sur ce telephone : c'est une commodite, jamais une autorisation (le serveur revalide tout).
 */

type Remembered = { eventId: number; day: string };

const keyFor = (tenantSlug: string) => `convive.entry-control.${tenantSlug}`;

const today = () => new Date().toISOString().slice(0, 10);

export function rememberEntryControl(
    tenantSlug: string,
    eventId: number,
): void {
    try {
        window.localStorage.setItem(
            keyFor(tenantSlug),
            JSON.stringify({ eventId, day: today() } satisfies Remembered),
        );
    } catch {
        // Stockage indisponible : l'agent refera simplement son choix.
    }
}

export function recallEntryControl(tenantSlug: string): number | null {
    try {
        const raw = window.localStorage.getItem(keyFor(tenantSlug));
        const value: unknown = raw ? JSON.parse(raw) : null;

        if (
            typeof value === 'object' &&
            value !== null &&
            'eventId' in value &&
            'day' in value &&
            typeof value.eventId === 'number' &&
            value.day === today()
        ) {
            return value.eventId;
        }

        return null;
    } catch {
        return null;
    }
}

export function forgetEntryControl(tenantSlug: string): void {
    try {
        window.localStorage.removeItem(keyFor(tenantSlug));
    } catch {
        // Rien a oublier si le stockage est indisponible.
    }
}
