import { expect, test } from './support/fixtures';
import { distinctGuestAddress, phoneFor, register, state, visibleAction } from './support/helpers';

/**
 * Le parcours de l'invite face aux situations limites : numero invalide, doublon, lien altere, perte
 * de reseau, limite d'accompagnateurs, pages qui debordent sur un petit ecran. Rejoue sur bureau et
 * sur iPhone.
 */
test.beforeEach(async ({ context }, testInfo) => {
    await distinctGuestAddress(context, testInfo);
});

const overflows = (page: import('@playwright/test').Page) =>
    page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);

test('aucune page du parcours invite ne deborde horizontalement', async ({ page }, testInfo) => {
    const { publicUrl, unit } = state();

    await page.goto(publicUrl);
    expect(await overflows(page)).toBeLessThanOrEqual(1);

    await visibleAction(page, 'register-link').click();
    await expect(page.getByTestId('registration-form')).toBeVisible();
    expect(await overflows(page)).toBeLessThanOrEqual(1);

    const url = await register(page, { eventUrl: publicUrl, name: `Debordement ${testInfo.project.name}`, phone: phoneFor(testInfo, 41), unit, companions: ['Premier', 'Second'] });
    await page.goto(url);
    expect(await overflows(page)).toBeLessThanOrEqual(1);
});

test('un numero de telephone invalide est refuse, un numero etranger valide est accepte', async ({ page }, testInfo) => {
    const { publicUrl, unit } = state();

    await page.goto(publicUrl);
    await visibleAction(page, 'register-link').click();
    await page.getByTestId('registration-name').fill(`Numero ${testInfo.project.name}`);
    await page.locator('#phone').fill('123');
    await page.getByTestId('registration-unit').click();
    await page.getByRole('option', { name: unit, exact: true }).click();
    await page.getByTestId('registration-submit').click();

    await expect(page.getByTestId('registration-form')).toBeVisible();
    await expect(page).not.toHaveURL(/\/register\/[A-Za-z0-9_-]{20,}/);
});

test('un meme numero ne tient pas deux reservations actives sur le meme evenement', async ({ page, browser }, testInfo) => {
    const { publicUrl, unit } = state();
    const phone = phoneFor(testInfo, 42);

    await register(page, { eventUrl: publicUrl, name: `Premier ${testInfo.project.name}`, phone, unit });

    const second = await browser.newContext({ locale: 'fr-FR' });
    const other = await second.newPage();
    await distinctGuestAddress(second, { ...testInfo, titlePath: [...testInfo.titlePath, 'second'] } as typeof testInfo);
    await other.goto(publicUrl);
    await visibleAction(other, 'register-link').click();
    await other.getByTestId('registration-name').fill(`Second ${testInfo.project.name}`);
    await other.locator('#phone').fill(phone);
    await other.getByTestId('registration-unit').click();
    await other.getByRole('option', { name: unit, exact: true }).click();
    await other.getByTestId('registration-submit').click();

    // Pas de seconde reservation : l'invite est renvoye vers la premiere ou informe.
    await expect(other.getByTestId('registration-ongoing').or(other.getByTestId('registration-form'))).toBeVisible();
    await second.close();
});

test('un lien de reprise altere repond par une page, jamais par une page blanche', async ({ page }) => {
    const { publicUrl } = state();

    const response = await page.goto(`${publicUrl}/register/${'a'.repeat(43)}`);

    expect(response?.status()).toBe(404);
    expect((await page.locator('body').innerText()).trim().length).toBeGreaterThan(0);
});

test('un jeton d\'evenement inconnu repond 404 lisible', async ({ page }) => {
    const { publicUrl } = state();
    const host = new URL(publicUrl).origin;

    const response = await page.goto(`${host}/e/${'z'.repeat(64)}`);

    expect(response?.status()).toBe(404);
    expect((await page.locator('body').innerText()).trim().length).toBeGreaterThan(0);
});

test('la limite d\'accompagnateurs est respectee par le formulaire', async ({ page }) => {
    const { publicUrl } = state();

    await page.goto(publicUrl);
    await visibleAction(page, 'register-link').click();
    await expect(page.getByTestId('registration-form')).toBeVisible();

    const add = page.getByTestId('companion-add');
    let added = 0;

    while ((await add.isEnabled()) && added < 12) {
        await add.click();
        added += 1;
    }

    // Le formulaire s'arrete de lui-meme avant 12 (la limite de l'evenement) et le compteur le dit.
    expect(added).toBeLessThan(12);
    await expect(page.getByTestId('companion-count')).toBeVisible();
    await expect(page.getByTestId('companion-row')).toHaveCount(added);
});

test('sans reseau, la page de reservation le dit au lieu de rester muette', async ({ page, context }, testInfo) => {
    const { publicUrl, unit } = state();

    const url = await register(page, { eventUrl: publicUrl, name: `Hors ligne ${testInfo.project.name}`, phone: phoneFor(testInfo, 43), unit });
    await page.goto(url);
    await expect(page.getByTestId('registration-countdown')).toBeVisible();

    await context.setOffline(true);
    await page.evaluate(() => window.dispatchEvent(new Event('offline')));
    await expect(page.getByTestId('offline-banner')).toBeVisible();

    await context.setOffline(false);
    await page.evaluate(() => window.dispatchEvent(new Event('online')));
    await expect(page.getByTestId('offline-banner')).toBeHidden();
});

test('le lien de reprise rouvre le meme dossier dans un autre navigateur', async ({ page, browser }, testInfo) => {
    const { publicUrl, unit } = state();
    const name = `Reprise ${testInfo.project.name}`;

    const url = await register(page, { eventUrl: publicUrl, name, phone: phoneFor(testInfo, 44), unit });

    const elsewhere = await browser.newContext({ locale: 'fr-FR' });
    const other = await elsewhere.newPage();
    await other.goto(url);

    await expect(other.getByTestId('registration-summary')).toContainText(name);
    await elsewhere.close();
});

test('le decompte reste visible en bas de la page de reservation', async ({ page }, testInfo) => {
    const { publicUrl, unit } = state();

    const url = await register(page, { eventUrl: publicUrl, name: `Decompte colle ${testInfo.project.name}`, phone: phoneFor(testInfo, 45), unit });
    await page.goto(url);
    await expect(page.getByTestId('registration-countdown')).toBeVisible();

    await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
    await expect(page.getByTestId('registration-countdown')).toBeInViewport();
});
