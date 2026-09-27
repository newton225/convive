/**
 * La file locale des scans faits hors ligne (README 2.8, CLAUDE.md, « PWA »), gardee dans le
 * `localStorage` de l'appareil de l'agent, une file par evenement. Le stockage peut etre absent ou
 * refuse (navigation privee, quota) : chaque acces est protege, et la file retombe alors sur vide
 * plutot que de casser le scan.
 */

export type QueuedScan = {
    token: string;
    scannedAt: string;
};

const queueKey = (eventId: number) => `convive:scan-queue:${eventId}`;
const seenKey = (eventId: number) => `convive:scan-seen:${eventId}`;

function readStored(key: string): unknown {
    try {
        const raw = localStorage.getItem(key);

        return raw === null ? null : JSON.parse(raw);
    } catch {
        return null;
    }
}

function write(key: string, value: unknown): void {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch {
        // Stockage indisponible : la file ne survivra pas a un rechargement, le scan continue.
    }
}

const isQueuedScan = (value: unknown): value is QueuedScan =>
    typeof value === 'object' &&
    value !== null &&
    'token' in value &&
    typeof value.token === 'string' &&
    'scannedAt' in value &&
    typeof value.scannedAt === 'string';

export function readQueue(eventId: number): QueuedScan[] {
    const stored = readStored(queueKey(eventId));

    return Array.isArray(stored) ? stored.filter(isQueuedScan) : [];
}

export function writeQueue(eventId: number, queue: QueuedScan[]): void {
    write(queueKey(eventId), queue);
}

const seenEntry = (registrationId: number, holder: number) =>
    `${registrationId}:${holder}`;

function readSeen(eventId: number): string[] {
    const stored = readStored(seenKey(eventId));

    if (!Array.isArray(stored)) {
        return [];
    }

    // Un nombre seul vient d'avant le billet par personne : il designait le billet de l'invite.
    return stored.flatMap((entry) =>
        typeof entry === 'string'
            ? [entry]
            : typeof entry === 'number'
              ? [seenEntry(entry, 0)]
              : [],
    );
}

/**
 * Les billets deja acceptes hors ligne sur cet appareil : deux scans du meme billet hors ligne
 * doivent etre signales, le serveur ne peut pas encore le faire. Un billet par personne (README
 * 2.8) : la cle porte le titulaire, un accompagnateur arrive seul n'empeche pas l'invite d'entrer.
 */
export function hasSeenLocally(
    eventId: number,
    registrationId: number,
    holder: number,
): boolean {
    return readSeen(eventId).includes(seenEntry(registrationId, holder));
}

export function markSeenLocally(
    eventId: number,
    registrationId: number,
    holder: number,
): void {
    write(seenKey(eventId), [
        ...readSeen(eventId),
        seenEntry(registrationId, holder),
    ]);
}

const revocationsKey = (eventId: number) =>
    `convive:scan-revocations:${eventId}`;

/**
 * La liste de revocation signee (SECURITY.md C2) est gardee telle que recue, signature comprise, et
 * reverifiee a chaque lecture : modifier le stockage local ne permet pas de la vider en silence.
 */
export function readStoredRevocationList(eventId: number): string | null {
    const stored = readStored(revocationsKey(eventId));

    return typeof stored === 'string' ? stored : null;
}

export function storeRevocationList(eventId: number, token: string): void {
    write(revocationsKey(eventId), token);
}

/**
 * Efface la file locale, les billets deja vus et la liste de revocation d'un evenement. Appelee
 * des que l'evenement est clos (SECURITY.md M8) : noms et numeros de table n'ont plus a rester sur
 * l'appareil, et le serveur refuserait de toute facon les passages encore en file.
 */
export function clearEventScanStorage(eventId: number): void {
    try {
        [queueKey(eventId), seenKey(eventId), revocationsKey(eventId)].forEach(
            (key) => localStorage.removeItem(key),
        );
    } catch {
        // Stockage indisponible : rien a effacer.
    }
}

/**
 * Efface toute la file locale et la liste des billets deja vus, pour tous les evenements. Appelee
 * a la deconnexion (SECURITY.md M8).
 */
export function clearScanStorage(): void {
    try {
        Object.keys(localStorage)
            .filter((key) => key.startsWith('convive:scan-'))
            .forEach((key) => localStorage.removeItem(key));
    } catch {
        // Stockage indisponible : rien a effacer.
    }
}
