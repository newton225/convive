/**
 * Verification hors ligne d'un billet (README 2.8, SECURITY.md C2). Le QR porte
 * `base64url(charge utile JSON) . base64url(signature Ed25519)` ; l'appareil de l'agent ne detient
 * que la cle publique de l'evenement, jamais un secret de signature. Meme format que
 * `App\Support\TicketToken` cote serveur.
 *
 * Ce controle prouve l'authenticite du billet, pas son etat : une inscription annulee depuis la
 * derniere synchronisation reste valide ici. C'est le serveur qui tranche, a la synchronisation
 * (le passage est alors signale « a revoir » s'il refuse le billet).
 */

export type TicketVerification =
    | { status: 'valid'; registrationId: number }
    | { status: 'forged' }
    | { status: 'wrong_event' }
    | { status: 'unsupported' }
    | { status: 'no_key' };

const decodeBase64 = (value: string): Uint8Array<ArrayBuffer> => {
    const normalised = value.replace(/-/g, '+').replace(/_/g, '/');
    const padded = normalised.padEnd(Math.ceil(normalised.length / 4) * 4, '=');
    const binary = atob(padded);

    return Uint8Array.from(binary, (char) => char.charCodeAt(0));
};

const isPayload = (
    value: unknown,
): value is { tenant_id: number; event_id: number; registration_id: number } =>
    typeof value === 'object' &&
    value !== null &&
    'tenant_id' in value &&
    'event_id' in value &&
    'registration_id' in value;

export async function verifyTicketOffline(
    token: string,
    publicKeyBase64: string | null,
    expected: { tenantId: number; eventId: number },
): Promise<TicketVerification> {
    if (publicKeyBase64 === null) {
        return { status: 'no_key' };
    }

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

        const payload: unknown = JSON.parse(
            new TextDecoder().decode(payloadBytes),
        );

        if (
            !isPayload(payload) ||
            payload.tenant_id !== expected.tenantId ||
            payload.event_id !== expected.eventId
        ) {
            return { status: 'wrong_event' };
        }

        return { status: 'valid', registrationId: payload.registration_id };
    } catch {
        return { status: 'forged' };
    }
}
