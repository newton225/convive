import { devices } from '@playwright/test';
import type { BrowserContext, Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { receiptImage, signIn, state } from './support/helpers';

/**
 * Le cycle de vie complet d'un evenement, de bout en bout et de tous les cotes : l'organisateur le
 * cree a deux tarifs et le publie ; un invite s'inscrit avec un accompagnateur a un autre tarif, relit son
 * recapitulatif, depose sa preuve ; l'organisateur la valide ; l'invite recoit son billet (page et PDF) ;
 * l'accueil le fait entrer sans scan, un second passage est signale ; l'invite ecrit une reclamation que
 * l'organisateur traite ; le dossier est annule avec un remboursement a faire, puis marque rembourse.
 *
 * Les etapes se suivent (meme evenement, memes personnes) : le groupe est `serial`. Seul le projet de
 * bureau le joue ; l'invite, lui, navigue avec un iPhone.
 */
test.describe.configure({ mode: 'serial' });

const guestName = 'Awa Cycle Complet';
const companionName = 'Moussa Cycle Complet';

let eventId = 0;
let publicUrl = '';
let reservationUrl = '';
let owner: Page;
let ownerContext: BrowserContext;

test.beforeAll(async ({ browser }) => {
    ownerContext = await browser.newContext({ locale: 'fr-FR', viewport: { width: 1400, height: 1000 } });
    owner = await ownerContext.newPage();
    await signIn(owner);
});

test.afterAll(async () => {
    await ownerContext.close();
});

async function guestPage(browser: import('@playwright/test').Browser): Promise<{ context: BrowserContext; page: Page }> {
    const context = await browser.newContext({
        ...devices['iPhone 14'],
        locale: 'fr-FR',
        extraHTTPHeaders: { 'X-Forwarded-For': '10.77.12.34' },
    });

    return { context, page: await context.newPage() };
}

test('l\'organisateur cree un evenement a deux tarifs et le publie', async () => {
    const { tenantSlug } = state();

    await owner.goto(`/${tenantSlug}/events/new`);

    await owner.getByTestId('event-name').fill('Gala du cycle complet');
    await owner.getByTestId('event-starts_at').fill('2027-06-12T19:00');
    await owner.getByTestId('event-ends_at').fill('2027-06-12T23:30');
    await owner.getByTestId('event-venue').fill('Hotel Ivoire, Abidjan');

    if ((await owner.getByTestId('event-table-group-count').count()) === 0) {
        await owner.getByTestId('event-table-group-add').click();
    }

    await owner.getByTestId('event-table-group-count').first().fill('5');
    await owner.getByTestId('event-table-group-seats').first().fill('8');

    // Deux tarifs, chacun avec son quota (obligatoire) : 30 + 10 places dans une salle de 40.
    const rows = owner.getByTestId('price-category-row');
    await rows.first().getByTestId('price-category-name').fill('Standard');
    await rows.first().getByTestId('price-category-price').fill('10000');
    await rows.first().getByTestId('price-category-quota').fill('30');
    await owner.getByTestId('price-category-add').click();
    await rows.nth(1).getByTestId('price-category-name').fill('Gratuit');
    await rows.nth(1).getByTestId('price-category-price').fill('0');
    await rows.nth(1).getByTestId('price-category-quota').fill('10');

    await owner.getByTestId('event-payment-account').first().click();
    await owner.getByTestId('event-submit').click();

    await owner.waitForURL(/\/events\/\d+(\?.*)?$/);
    eventId = Number(owner.url().match(/events\/(\d+)/)?.[1]);
    expect(eventId).toBeGreaterThan(0);

    await owner.getByTestId('event-publish').click();
    await owner.getByTestId('event-publish-acknowledge').click();
    await owner.getByTestId('event-publish-confirm').click();
    await expect(owner.getByTestId('event-published')).toBeVisible();

    // Les props de la page, telles qu'Inertia les envoie : on y lit l'adresse publique de l'evenement.
    const version = await owner.evaluate(() => JSON.parse(document.querySelector('script[data-page="app"]')?.textContent ?? '{}').version);
    const json = await owner.request.get(`/${tenantSlug}/events/${eventId}`, {
        headers: { 'X-Inertia': 'true', 'X-Inertia-Version': String(version), 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    });
    publicUrl = (await json.json()).props.event.publicUrl;
    expect(publicUrl).toContain('/e/');
});

test('un invite s\'inscrit avec un accompagnateur a un autre tarif et relit son recapitulatif', async ({ browser }) => {
    const { unit } = state();
    const { context, page } = await guestPage(browser);

    await page.goto(publicUrl);
    await page.getByTestId(/^register-link(-mobile)?$/).filter({ visible: true }).first().click();

    await page.getByTestId('registration-name').fill(guestName);
    await page.locator('#phone').fill('0707123401');
    await page.getByTestId('registration-unit').click();
    await page.getByRole('option', { name: unit, exact: true }).click();
    await page.getByTestId('registration-price-category').click();
    await page.getByRole('option', { name: /Standard/ }).click();

    await page.getByTestId('companion-add').click();
    await page.getByTestId('companion-name').first().fill(companionName);
    await page.getByTestId('companion-unit').first().evaluate((element) => element.scrollIntoView({ block: 'center' }));
    await page.getByTestId('companion-unit').first().click();
    await page.getByRole('option', { name: unit, exact: true }).click();
    await page.getByTestId('companion-price-category').first().click();
    await page.getByRole('option', { name: /Gratuit/ }).click();

    // Le montant suit les tarifs choisis : 10 000 + 0.
    await expect(page.getByTestId('registration-total')).toContainText('10');
    await page.getByTestId('registration-submit').click();
    await expect(page.getByTestId('registration-countdown')).toBeVisible();
    reservationUrl = page.url();

    // Le recapitulatif : personnes, tarifs, detail par tarif, coordonnees et total.
    const summary = page.getByTestId('registration-summary');
    await expect(summary).toContainText(guestName);
    await expect(summary).toContainText(companionName);
    await expect(summary.getByTestId('registration-summary-person')).toHaveCount(2);
    await expect(summary.getByTestId('registration-summary-line')).toHaveCount(2);
    await expect(summary.getByTestId('registration-summary-total')).toContainText('10');
    await expect(summary).toContainText('Gratuit');

    await context.close();
});

test('l\'invite depose sa preuve et son recapitulatif reste a l\'ecran', async ({ browser }) => {
    const { context, page } = await guestPage(browser);

    await page.goto(reservationUrl);
    await page.getByTestId('proof-account').click();
    await page.getByRole('option').first().click();
    await page.getByTestId('proof-reference').fill('WV-CYCLE-0001');
    await page.getByTestId('proof-receipt').setInputFiles(receiptImage());
    await page.getByTestId('proof-submit').click();

    await expect(page.getByTestId('registration-proof-status')).toBeVisible();
    await expect(page.getByTestId('registration-summary')).toBeVisible();
    await expect(page.getByTestId('registration-summary-person')).toHaveCount(2);

    await context.close();
});

test('l\'organisateur valide la preuve, l\'invite recoit son billet et son PDF', async ({ browser }) => {
    const { tenantSlug } = state();

    await owner.goto(`/${tenantSlug}/events/${eventId}/proofs`);
    const row = owner.getByTestId('proof-row').filter({ hasText: guestName });
    await expect(row).toBeVisible();
    await row.getByTestId('proof-approve').click();
    await owner.getByTestId('proof-approve-confirm').click();
    await expect(row).toBeHidden();

    const { context, page } = await guestPage(browser);

    await page.goto(reservationUrl);
    await expect(page.getByTestId('ticket-card')).toBeVisible();

    const pdf = page.getByTestId('ticket-pdf').first().locator('a[download]');
    await expect(pdf).toBeVisible();
    // Le telechargement lui-meme : un vrai PDF, pas une page d'erreur renommee.
    const [download] = await Promise.all([page.waitForEvent('download'), pdf.click()]);
    const file = await (await import('node:fs')).promises.readFile(await download.path());
    expect(file.subarray(0, 4).toString()).toBe('%PDF');
    expect(file.length).toBeGreaterThan(1000);

    await context.close();
});

test('a l\'entree : recherche par nom, validation, puis un second passage signale', async () => {
    const { tenantSlug } = state();

    await owner.goto(`/${tenantSlug}/events/${eventId}/scan`);

    // Un code de scan peut etre demande a la premiere ouverture : on le pose s'il l'exige.
    const pin = owner.getByTestId('scan-pin-form');

    if (await pin.isVisible().catch(() => false)) {
        for (const field of await pin.locator('input[type="password"], input[inputmode="numeric"]').all()) {
            await field.fill('482916');
        }

        await owner.getByTestId('scan-pin-submit').click();
    }

    await owner.getByTestId('guest-lookup-search').fill('Cycle Complet');
    await owner.getByTestId('guest-lookup-submit').click();

    // Un billet par personne : la recherche rend l'invite puis son accompagnateur (« accompagnateur de... »).
    const lookup = owner.getByTestId('guest-lookup-row').filter({ hasText: guestName }).first();
    await expect(lookup).toBeVisible();
    await lookup.getByTestId('guest-lookup-admit').click();
    // La confirmation : l'entree sans scan est tracee au nom de l'agent et ne s'annule pas.
    await owner.getByRole('dialog').getByRole('button', { name: /Valider l.entrée/ }).click();
    await expect(owner.getByTestId('scan-result')).toBeVisible();

    // Le meme billet une seconde fois : l'ecran le dit.
    await owner.getByTestId('guest-lookup-search').fill('Cycle Complet');
    await owner.getByTestId('guest-lookup-submit').click();
    await expect(owner.getByTestId('guest-lookup-row').filter({ hasText: guestName }).first().getByTestId('guest-lookup-arrived')).toBeVisible();
});

test('l\'invite ecrit une reclamation, l\'organisateur la lit et la traite', async ({ browser }) => {
    const { tenantSlug } = state();
    const { context, page } = await guestPage(browser);

    await page.goto(reservationUrl);
    const form = page.getByTestId('guest-claim-form');
    await form.scrollIntoViewIfNeeded();
    await page.getByTestId('guest-claim-category').click();
    await page.getByRole('option', { name: 'Mon paiement' }).click();
    await page.getByTestId('guest-claim-message').fill('Bonjour, ma preuve a ete validee mais je voudrais une facture.');
    await page.getByTestId('guest-claim-submit').click();
    await expect(page.getByText('Votre réclamation est envoyée')).toBeVisible();
    await context.close();

    await owner.goto(`/${tenantSlug}/events/${eventId}/claims`);
    const claim = owner.getByTestId('claim-row').filter({ hasText: guestName });
    await expect(claim).toBeVisible();
    await expect(claim).toContainText('une facture');
    await claim.getByTestId('claim-resolve').click();
    // Le repere `claim-resolve-dialog` est celui du bouton de confirmation.
    await owner.getByTestId('claim-resolve-dialog').click();
    await expect(owner.getByTestId('claim-row').filter({ hasText: guestName })).toHaveCount(0);

    // Elle reste lisible dans les reclamations traitees.
    await owner.goto(`/${tenantSlug}/events/${eventId}/claims?filter[status]=resolved`);
    await expect(owner.getByTestId('claim-row').filter({ hasText: guestName })).toBeVisible();
});

test('le dossier s\'exporte en CSV avec l\'invite et son accompagnateur', async () => {
    const { tenantSlug } = state();

    await owner.goto(`/${tenantSlug}/events/${eventId}/registrations`);
    await expect(owner.getByText(guestName)).toBeVisible();

    const download = owner.waitForEvent('download');
    await owner.getByTestId('registrations-export-csv').click();
    const file = await download;
    const content = (await (await import('node:fs')).promises.readFile(await file.path(), 'utf8'));

    expect(content).toContain(guestName);
    // Une ligne par dossier : le groupe de deux personnes et le montant des deux tarifs (10 000 + gratuit).
    expect(content).toContain('"2";"10000";"Validée"');
});

test('l\'annulation d\'un dossier paye propose un remboursement, marque rembourse ensuite', async () => {
    const { tenantSlug } = state();

    await owner.goto(`/${tenantSlug}/events/${eventId}/registrations`);
    const row = owner.getByTestId('registration-row').filter({ hasText: guestName });
    await expect(row).toBeVisible();
    await row.getByTestId('registration-cancel').click();
    await owner.getByTestId('registration-cancel-reason').fill('Empechement de l\'invitee');
    await owner.getByTestId('registration-cancel-confirm').click();

    const cancellations = owner.getByTestId('registration-cancellations');
    await expect(cancellations).toContainText(guestName);
    await expect(cancellations).toContainText('rembourser');

    await cancellations.getByTestId('refund-mark').first().click();
    await owner.getByTestId('refund-channel').click();
    await owner.getByRole('option').first().click();
    await owner.getByTestId('refund-fee').fill('200');
    await owner.getByTestId('refund-reference').fill('REMB-CYCLE-0001');
    await owner.getByTestId('record-refund-confirm').click();
    await expect(cancellations).toContainText('Remboursé');
});
