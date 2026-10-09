import { describe, expect, it } from 'vite-plus/test';
import { verifyTicketOffline } from '@/lib/ticket-verifier';
import type { ExpectedEvent } from '@/lib/ticket-verifier';

const encode = (bytes: Uint8Array<ArrayBuffer>): string =>
    btoa(String.fromCharCode(...bytes))
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/, '');

// Signe un billet comme `App\Support\TicketToken`, avec une paire de cles Ed25519 de test.
async function signedTicket(payload: Record<string, unknown>) {
    const pair = await crypto.subtle.generateKey({ name: 'Ed25519' }, true, [
        'sign',
        'verify',
    ]);
    const body = new TextEncoder().encode(JSON.stringify(payload));
    const signature = new Uint8Array(
        await crypto.subtle.sign({ name: 'Ed25519' }, pair.privateKey, body),
    );
    const publicKey = new Uint8Array(
        await crypto.subtle.exportKey('raw', pair.publicKey),
    );

    return {
        token: `${encode(body)}.${encode(signature)}`,
        publicKey: btoa(String.fromCharCode(...publicKey)),
    };
}

const now = () => Math.floor(Date.now() / 1000);

const payload = (notAfter: number | null) => ({
    tenant_id: 1,
    event_id: 7,
    registration_id: 42,
    key_version: 1,
    not_after: notAfter,
});

const expected = (overrides: Partial<ExpectedEvent> = {}): ExpectedEvent => ({
    tenantId: 1,
    eventId: 7,
    keyVersion: 1,
    validUntil: null,
    validFrom: null,
    ...overrides,
});

describe('verifyTicketOffline : fenetre d\u2019entree', () => {
    it('accepte un billet sans heure d\u2019ouverture', async () => {
        const { token, publicKey } = await signedTicket(payload(now() + 3600));

        const result = await verifyTicketOffline(
            token,
            publicKey,
            expected(),
            [],
        );

        expect(result.status).toBe('valid');
    });

    it('refuse un billet avant l\u2019ouverture des portes', async () => {
        const { token, publicKey } = await signedTicket(payload(now() + 7200));

        const result = await verifyTicketOffline(
            token,
            publicKey,
            expected({ validFrom: now() + 3600 }),
            [],
        );

        expect(result.status).toBe('too_early');
    });

    it('accepte un billet une fois les portes ouvertes', async () => {
        const { token, publicKey } = await signedTicket(payload(now() + 7200));

        const result = await verifyTicketOffline(
            token,
            publicKey,
            expected({ validFrom: now() - 60 }),
            [],
        );

        expect(result.status).toBe('valid');
    });

    it('refuse un billet apres sa marge, la plus tardive des echeances faisant foi', async () => {
        const { token, publicKey } = await signedTicket(payload(now() - 600));

        const late = await verifyTicketOffline(
            token,
            publicKey,
            expected({ validUntil: now() - 300 }),
            [],
        );
        const extended = await verifyTicketOffline(
            token,
            publicKey,
            expected({ validUntil: now() + 600 }),
            [],
        );

        expect(late.status).toBe('expired');
        expect(extended.status).toBe('valid');
    });
});
