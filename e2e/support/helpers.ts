import fs from 'node:fs';
import zlib from 'node:zlib';
import type { Page } from '@playwright/test';
import { account, statePath } from '../environment';
import type { State } from '../environment';

/**
 * Ce que la mise en place a note : l'organisation de demonstration et un evenement ouvert.
 */
export function state(): State {
    return JSON.parse(fs.readFileSync(statePath, 'utf8')) as State;
}

/**
 * Connecte le compte de demonstration (Proprietaire de l'organisation, sans double
 * authentification dans cet environnement).
 */
export async function signIn(page: Page): Promise<void> {
    await page.goto('/login');
    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.getByTestId('login-button').click();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'));
}

/**
 * Une image PNG unie, fabriquee sans fichier ni dependance : la capture qu'un invite depose comme
 * preuve. 200 x 400 pixels, au-dessus du minimum que la validation exige (100 x 100).
 */
export function receiptImage(): {
    name: string;
    mimeType: string;
    buffer: Buffer;
} {
    const width = 200;
    const height = 400;
    // Une ligne : l'octet de filtre, puis trois octets par pixel.
    const row = Buffer.concat([
        Buffer.from([0]),
        Buffer.alloc(width * 3, 0xd0),
    ]);
    const pixels = Buffer.concat(Array.from({ length: height }, () => row));

    const chunk = (type: string, data: Buffer) => {
        const body = Buffer.concat([Buffer.from(type, 'ascii'), data]);
        const length = Buffer.alloc(4);
        const checksum = Buffer.alloc(4);

        length.writeUInt32BE(data.length);
        checksum.writeUInt32BE(zlib.crc32(body));

        return Buffer.concat([length, body, checksum]);
    };

    const header = Buffer.alloc(13);
    header.writeUInt32BE(width, 0);
    header.writeUInt32BE(height, 4);
    // Profondeur 8 bits, couleurs RVB, sans entrelacement.
    header.set([8, 2, 0, 0, 0], 8);

    return {
        name: 'recu.png',
        mimeType: 'image/png',
        buffer: Buffer.concat([
            Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
            chunk('IHDR', header),
            chunk('IDAT', zlib.deflateSync(pixels)),
            chunk('IEND', Buffer.alloc(0)),
        ]),
    };
}
