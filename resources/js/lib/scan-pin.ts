/**
 * Code de scan a 4 chiffres (SECURITY.md M8). Le serveur ne transmet qu'une empreinte PBKDF2 salee ;
 * l'appareil recalcule l'empreinte du code saisi et compare, ce qui permet de deverrouiller l'ecran
 * de scan sans reseau. L'etat du verrou est garde dans le stockage local : recharger la page ne le
 * leve pas, et le nombre d'essais rates survit aussi au rechargement.
 */

export type ScanPinVerifier = {
    salt: string;
    hash: string;
    iterations: number;
};

export type ScanLockState = {
    locked: boolean;
    lastActivity: number;
    attempts: number;
};

// Sous le prefixe `convive:scan-` : efface a la deconnexion avec le reste des donnees de scan.
const lockKey = 'convive:scan-lock';

const decodeBase64 = (value: string): Uint8Array<ArrayBuffer> =>
    Uint8Array.from(atob(value), (char) => char.charCodeAt(0));

export async function verifyScanPin(
    pin: string,
    verifier: ScanPinVerifier,
): Promise<boolean> {
    if (typeof crypto === 'undefined' || !crypto.subtle) {
        return false;
    }

    const key = await crypto.subtle.importKey(
        'raw',
        new TextEncoder().encode(pin),
        'PBKDF2',
        false,
        ['deriveBits'],
    );

    const bits = new Uint8Array(
        await crypto.subtle.deriveBits(
            {
                name: 'PBKDF2',
                hash: 'SHA-256',
                salt: decodeBase64(verifier.salt),
                iterations: verifier.iterations,
            },
            key,
            256,
        ),
    );

    const expected = decodeBase64(verifier.hash);

    if (bits.length !== expected.length) {
        return false;
    }

    // Comparaison sur toute la longueur, sans sortie anticipee.
    let difference = 0;
    bits.forEach((byte, index) => {
        difference |= byte ^ expected[index];
    });

    return difference === 0;
}

const isLockState = (value: unknown): value is ScanLockState =>
    typeof value === 'object' &&
    value !== null &&
    'locked' in value &&
    typeof value.locked === 'boolean' &&
    'lastActivity' in value &&
    typeof value.lastActivity === 'number' &&
    'attempts' in value &&
    typeof value.attempts === 'number';

export function readLockState(): ScanLockState {
    try {
        const raw = localStorage.getItem(lockKey);
        const parsed: unknown = raw === null ? null : JSON.parse(raw);

        if (isLockState(parsed)) {
            return parsed;
        }
    } catch {
        // Stockage indisponible : l'etat repart de zero, l'ecran reste verrouillable en memoire.
    }

    return { locked: false, lastActivity: Date.now(), attempts: 0 };
}

export function writeLockState(state: ScanLockState): void {
    try {
        localStorage.setItem(lockKey, JSON.stringify(state));
    } catch {
        // Stockage indisponible : le verrou ne survivra pas a un rechargement.
    }
}
