import { expect, test } from '@playwright/test';
import {
    decideProof,
    phoneFor,
    register,
    state,
    submitProof,
} from './support/helpers';

/**
 * Le parcours critique du produit, de bout en bout (README section 2) : un invite s'inscrit par le
 * lien public, depose sa preuve de paiement, l'organisateur la valide ou la rejette, et l'invite
 * retrouve son billet ou la possibilite de recommencer. Rejoue sur iPhone.
 */
test('un invite s inscrit, depose sa preuve et recoit son billet apres validation', async ({
    page,
    browser,
}, testInfo) => {
    const { publicUrl, unit } = state();
    const name = `Aya Kouassi ${testInfo.project.name}`;

    // La page publique, le formulaire, puis la reservation avec son decompte.
    const reservationUrl = await register(page, {
        eventUrl: publicUrl,
        name,
        phone: phoneFor(testInfo, 1),
        unit,
    });

    await expect(page.getByTestId('payment-account').first()).toBeVisible();

    await submitProof(page, `WV1000${testInfo.project.name}`);

    // L'organisateur valide la preuve, dans une autre session de navigateur.
    await decideProof(browser, name, 'approve');

    // L'invite revient sur sa page : son billet l'attend.
    await page.goto(reservationUrl);

    await expect(page.getByTestId('ticket-card')).toBeVisible();
});

test('une preuve rejetee laisse l invite relancer son inscription', async ({
    page,
    browser,
}, testInfo) => {
    const { publicUrl, unit } = state();
    const name = `Kofi Diallo ${testInfo.project.name}`;

    const reservationUrl = await register(page, {
        eventUrl: publicUrl,
        name,
        phone: phoneFor(testInfo, 2),
        unit,
    });

    await submitProof(page, `WV2000${testInfo.project.name}`);
    await decideProof(browser, name, 'reject');

    await page.goto(reservationUrl);

    // Pas de billet : l'invite voit que sa preuve n'a pas ete retenue et peut recommencer.
    await expect(page.getByTestId('ticket-card')).toBeHidden();
    await expect(page.getByTestId('registration-retry')).toBeVisible();
});
