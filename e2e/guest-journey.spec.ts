import { expect, test } from '@playwright/test';
import { receiptImage, signIn, state } from './support/helpers';

/**
 * Le parcours critique du produit, de bout en bout (README section 2) : un invite s'inscrit par le
 * lien public, depose sa preuve de paiement, l'organisateur la valide, et l'invite recoit son
 * billet.
 *
 * Le numero de telephone est propre a chaque navigateur : une meme personne ne tient qu'une
 * reservation active par evenement, et les deux navigateurs partagent la meme base.
 */
test('un invite s inscrit, depose sa preuve et recoit son billet apres validation', async ({
    page,
    browser,
}, testInfo) => {
    const { publicUrl, tenantSlug, eventId, unit } = state();
    const phone =
        testInfo.project.name === 'iphone' ? '0707000002' : '0707000001';
    const guest = `Aya Kouassi ${testInfo.project.name}`;

    // 1. La page publique de l'evenement, sur le sous-domaine de l'organisation.
    await page.goto(publicUrl);
    await page.getByTestId('register-link').click();

    // 2. Le formulaire d'inscription.
    await expect(page.getByTestId('registration-form')).toBeVisible();
    await page.getByTestId('registration-name').fill(guest);
    await page.locator('#phone').fill(phone);
    await page.getByTestId('registration-unit').click();
    await page.getByRole('option', { name: unit, exact: true }).click();
    await page.getByTestId('registration-submit').click();

    // 3. La reservation : le decompte tourne, les comptes de versement sont affiches.
    await expect(page.getByTestId('registration-countdown')).toBeVisible();
    await expect(page.getByTestId('payment-account').first()).toBeVisible();
    const reservationUrl = page.url();

    // 4. Le depot de la preuve.
    await page.getByTestId('proof-account').click();
    await page.getByRole('option').first().click();
    await page
        .getByTestId('proof-reference')
        .fill(
            `WV${testInfo.project.name === 'iphone' ? '20000002' : '10000001'}`,
        );
    await page.getByTestId('proof-receipt').setInputFiles(receiptImage());
    await page.getByTestId('proof-submit').click();

    await expect(page.getByTestId('registration-proof-status')).toBeVisible();

    // 5. L'organisateur valide la preuve, dans une autre session de navigateur.
    const organiser = await browser.newContext({ locale: 'fr-FR' });
    const backOffice = await organiser.newPage();

    await signIn(backOffice);
    await backOffice.goto(`/${tenantSlug}/events/${eventId}/proofs`);

    const row = backOffice.getByTestId('proof-row').filter({ hasText: guest });

    await expect(row).toBeVisible();
    await row.getByTestId('proof-approve').click();
    await backOffice.getByTestId('proof-approve-confirm').click();
    await expect(row).toBeHidden();

    await organiser.close();

    // 6. L'invite revient sur sa page : son billet l'attend.
    await page.goto(reservationUrl);

    await expect(page.getByTestId('ticket-card')).toBeVisible();
});
