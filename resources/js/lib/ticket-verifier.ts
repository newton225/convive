/**
 * Verification hors ligne d'un billet (README 2.8, SECURITY.md C2). Le QR porte
 * `base64url(charge utile JSON) . base64url(signature Ed25519)` ; l'appareil de l'agent ne detient
 * que la cle publique de l'evenement, jamais un secret de signature. Meme format que
 * `App\Support\TicketToken` cote serveur.
 *
 * Ce controle prouve l'authenticite du billet, son echeance et sa version de cle, et le confronte a
 * la liste de revocation signee recue a la derniere synchronisation. Une inscription annulee apres
 * cette synchronisation reste valide ici : le serveur tranche au retour du reseau (le passage est
 * alors signale « a revoir » s'il refuse le billet).
 */

export type TicketVerification =
    | { status: 'valid'; registrationId: number }
    | { status: 'forged' }
    | { status: 'wrong_event' }
    | { status: 'outdated' }
    | { status: 'expired' }
    | { status: 'revoked' }
    | { status: 'unsupported' }
    | { status: 'no_key' };

export type ExpectedEvent = {
    tenantId: number;
    eventId: number;
    keyVersion: number;
    // Echeance que la date actuelle de l'evenement donne, en secondes. Un billet telecharge avant un
    // report porte une echeance plus ancienne : la plus tardive des deux fait foi, comme au serveur.
    validUntil: number | null;
};

type TicketPayload = {
    tenant_id: number;
    event_id: number;
    registration_id: number;
    key_version: number;
    not_after: number | null;
};

type RevocationPayload = {
    tenant_id: number;
    event_id: number;
    key_version: number;
    revoked: number[];
};

type SignedOutcome =
    | { status: 'authentic'; payload: unknown }
    | { status: 'forged' }
    | { status: 'unsupported' };

const decodeBase64 = (value: string): Uint8Array<ArrayBuffer> => {
    const normalised = value.replace(/-/g, '+').replace(/_/g, '/');
    const padded = normalised.padEnd(Math.ceil(normalised.length / 4) * 4, '=');
    const binary = atob(padded);

    return Uint8Array.from(binary, (char) => char.charCodeAt(0));
};

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

const isTicketPayload = (value: unknown): value is TicketPayload =>
    isRecord(value) &&
    typeof value.tenant_id === 'number' &&
    typeof value.event_id === 'number' &&
    typeof value.registration_id === 'number' &&
    typeof value.key_version === 'number' &&
    (value.not_after === null || typeof value.not_after === 'number');

const isRevocationPayload = (value: unknown): value is RevocationPayload =>
    isRecord(value) &&
    typeof value.tenant_id === 'number' &&
    typeof value.event_id === 'number' &&
    typeof value.key_version === 'number' &&
    Array.isArray(value.revoked) &&
    value.revoked.every((id) => typeof id === 'number');

/**
 * Verifie la signature Ed25519 d'une charge au format `TicketToken` et rend son contenu decode.
 */
async function openSigned(
    token: string,
    publicKeyBase64: string,
): Promise<SignedOutcome> {
    if (typeof crypto === 'undefined' || !crypto.subtle) {
        return { status: 'unsupported' };
    }

    const parts = token.split('.');

    if (parts.length !== 2) {
        return { status: 'forged' };
    }

    try {
        const payloadBytes = decodeBase64(parts[0]);
        const signature = decodeBase64(parts[1]);

        let key: CryptoKey;

        try {
            key = await crypto.subtle.importKey(
                'raw',
                decodeBase64(publicKeyBase64),
                { name: 'Ed25519' },
                false,
                ['verify'],
            );
        } catch {
            // Ed25519 n'est pas gere par ce navigateur (Chrome et Edge 137, Firefox 129, Safari 17).
            return { status: 'unsupported' };
        }

        const authentic = await crypto.subtle.verify(
            'Ed25519',
            key,
            signature,
            payloadBytes,
        );

        if (!authentic) {
            return { status: 'forged' };
        }

        return {
            status: 'authentic',
            payload: JSON.parse(new TextDecoder().decode(payloadBytes)),
        };
    } catch {
        return { status: 'forged' };
    }
}

/**
 * Verifie la liste de revocation recue du serveur et rend les inscriptions revoquees, ou null si la
 * liste est absente, falsifiee ou signee par une autre cle : l'appareil garde alors la precedente.
 */
export async function verifyRevocationList(
    token: string | null,
    publicKeyBase64: string | null,
    expected: ExpectedEvent,
): Promise<number[] | null> {
    if (token === null || publicKeyBase64 === null) {
        return null;
    }

    const opened = await openSigned(token, publicKeyBase64);

    if (
        opened.status !== 'authentic' ||
        !isRevocationPayload(opened.payload) ||
        opened.payload.tenant_id !== expected.tenantId ||
        opened.payload.event_id !== expected.eventId ||
        opened.payload.key_version !== expected.keyVersion
    ) {
        return null;
    }

    return opened.payload.revoked;
}

export async function verifyTicketOffline(
    token: string,
    publicKeyBase64: string | null,
    expected: ExpectedEvent,
    revoked: number[],
): Promise<TicketVerification> {
    if (publicKeyBase64 === null) {
        return { status: 'no_key' };
    }

    const opened = await openSigned(token, publicKeyBase64);

    if (opened.status !== 'authentic') {
        return { status: opened.status };
    }

    const payload = opened.payload;

    if (
        !isTicketPayload(payload) ||
        payload.tenant_id !== expected.tenantId ||
        payload.event_id !== expected.eventId
    ) {
        return { status: 'wrong_event' };
    }

    if (payload.key_version !== expected.keyVersion) {
        return { status: 'outdated' };
    }

    const deadlines = [payload.not_after, expected.validUntil].filter(
        (value): value is number => value !== null,
    );

    if (deadlines.length > 0 && Date.now() / 1000 > Math.max(...deadlines)) {
        return { status: 'expired' };
    }

    if (revoked.includes(payload.registration_id)) {
        return { status: 'revoked' };
    }

    return { status: 'valid', registrationId: payload.registration_id };
}
