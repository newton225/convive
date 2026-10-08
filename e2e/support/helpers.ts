import fs from 'node:fs';
import zlib from 'node:zlib';
import { expect } from '@playwright/test';
import type {
    Browser,
    BrowserContext,
    Locator,
    Page,
    TestInfo,
} from '@playwright/test';
import { account, statePath } from '../environment';
import type { State } from '../environment';

/**
 * Ce que la mise en place a note : l'organisation de demonstration, ses evenements, son tarif.
 */
export function state(): State {
    return JSON.parse(fs.readFileSync(statePath, 'utf8')) as State;
}

/**
 * Connecte un compte de demonstration. Par defaut le Proprietaire ; dans cet environnement aucun
 * compte n'a de double authentification a saisir.
 */
export async function signIn(
    page: Page,
    email: string = account.email,
): Promise<void> {
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(account.password);
    await page.getByTestId('login-button').click();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'));
}

/**
 * Un numero de telephone ivoirien propre a un test et a un navigateur. Une meme personne ne tient
 * qu'une reservation active par evenement, et tous les parcours partagent la meme base : deux tests
 * ne doivent jamais se presenter sous le meme numero.
 */
export function phoneFor(testInfo: TestInfo, sequence: number): string {
    const browser = testInfo.project.name === 'iphone' ? 2 : 1;

    return `07070${browser}${String(sequence).padStart(2, '0')}00`;
}

/**
 * Remplit le formulaire d'inscription du lien public et l'envoie. Rend l'adresse de la page de
 * reservation, celle sur laquelle l'invite revient.
 */
/**
 * Donner a l'invite de ce test sa propre adresse, comme deux vrais invites n'ont pas la meme.
 * L'adresse varie aussi par sous-reseau : la limite par sous-reseau est de 20 inscriptions.
 */
export async function distinctGuestAddress(
    context: BrowserContext,
    testInfo: TestInfo,
): Promise<void> {
    let hash = 0;

    for (const character of `${testInfo.project.name}|${testInfo.titlePath.join('|')}|${testInfo.retry}`) {
        hash = (hash * 31 + character.charCodeAt(0)) >>> 0;
    }

    const address = `10.${(hash >>> 16) & 255}.${(hash >>> 8) & 255}.${(hash & 254) + 1}`;

    await context.setExtraHTTPHeaders({ 'X-Forwarded-For': address });
}

/**
 * Le bouton d'action de la page publique qui est visible : celui de la page sur ordinateur, celui de
 * la barre du bas sur telephone (`-mobile`). Les deux existent, un seul se voit.
 */
export function visibleAction(page: Page, testId: string): Locator {
    return page
        .getByTestId(new RegExp(`^${testId}(-mobile)?$`))
        .filter({ visible: true })
        .first();
}

export async function register(
    page: Page,
    guest: {
        eventUrl: string;
        name: string;
        phone: string;
        unit: string;
        companions?: string[];
    },
): Promise<string> {
    await page.goto(guest.eventUrl);
    await visibleAction(page, 'register-link').click();

    await expect(page.getByTestId('registration-form')).toBeVisible();
    await page.getByTestId('registration-name').fill(guest.name);
    await page.locator('#phone').fill(guest.phone);
    await page.getByTestId('registration-unit').click();
    await page.getByRole('option', { name: guest.unit, exact: true }).click();

    for (const [index, companion] of (guest.companions ?? []).entries()) {
        await page.getByTestId('companion-add').click();
        await page.getByTestId('companion-name').nth(index).fill(companion);
        // Chaque accompagnateur porte aussi son tarif : sur un telephone, la liste d'unites du
        // dernier s'ouvrirait hors de l'ecran si le champ n'etait pas d'abord amene en vue.
        const unit = page.getByTestId('companion-unit').nth(index);
        await unit.evaluate((element) =>
            element.scrollIntoView({ block: 'center' }),
        );
        await unit.click();
        await page
            .getByRole('option', { name: guest.unit, exact: true })
            .click();
    }

    await page.getByTestId('registration-submit').click();
    await expect(page.getByTestId('registration-countdown')).toBeVisible();

    return page.url();
}

/**
 * Depose une preuve de paiement sur la page de reservation ouverte.
 */
export async function submitProof(
    page: Page,
    reference: string,
): Promise<void> {
    await page.getByTestId('proof-account').click();
    await page.getByRole('option').first().click();
    await page.getByTestId('proof-reference').fill(reference);
    await page.getByTestId('proof-receipt').setInputFiles(receiptImage());
    await page.getByTestId('proof-submit').click();

    await expect(page.getByTestId('registration-proof-status')).toBeVisible();
}

/**
 * Ouvre la file des preuves de l'evenement dans une session a part, celle de l'organisateur, y
 * applique un geste sur la preuve de l'invite nomme, puis referme la session.
 */
export async function decideProof(
    browser: Browser,
    guestName: string,
    decision: 'approve' | 'reject',
): Promise<void> {
    const { tenantSlug, eventId } = state();
    const context = await browser.newContext({ locale: 'fr-FR' });
    const page = await context.newPage();

    await signIn(page);
    await page.goto(`/${tenantSlug}/events/${eventId}/proofs`);

    const row = page.getByTestId('proof-row').filter({ hasText: guestName });

    await expect(row).toBeVisible();
    await row.getByTestId(`proof-${decision}`).click();
    await page.getByTestId(`proof-${decision}-confirm`).click();
    // Une preuve tranchee quitte la file.
    await expect(row).toBeHidden();

    await context.close();
}

/**
 * Les chiffres d'un montant affiche, sans l'espacement des milliers ni le libelle de la devise :
 * `Intl` ne separe pas les milliers par le meme caractere d'un navigateur a l'autre.
 */
export function digitsOf(text: string | null): string {
    return (text ?? '').replace(/\D/g, '');
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
