import { expect, test } from '@playwright/test';
import { digitsOf, phoneFor, register, state } from './support/helpers';

/**
 * Le formulaire d'inscription et ce qui l'entoure sur le lien public : accompagnateurs et montant,
 * liste d'attente d'un evenement complet, langue de l'invite. Rejoue sur iPhone.
 */
test.describe('inscription par le lien public', () => {
    test('le montant suit le nombre d accompagnateurs', async ({ page }) => {
        const { publicUrl, pricePerPerson } = state();

        await page.goto(publicUrl);
        await page.getByTestId('register-link').click();

        // Seul, puis avec deux accompagnateurs : le total se recalcule sans recharger la page.
        await expect(page.getByTestId('registration-total')).toBeVisible();
        expect(
            digitsOf(
                await page.getByTestId('registration-total').textContent(),
            ),
        ).toBe(String(pricePerPerson));

        await page.getByTestId('companion-add').click();
        await page.getByTestId('companion-add').click();

        await expect(page.getByTestId('companion-row')).toHaveCount(2);
        expect(
            digitsOf(
                await page.getByTestId('registration-total').textContent(),
            ),
        ).toBe(String(pricePerPerson * 3));

        // Un accompagnateur retire, le total redescend.
        await page.getByTestId('companion-remove').first().click();

        await expect(page.getByTestId('companion-row')).toHaveCount(1);
        expect(
            digitsOf(
                await page.getByTestId('registration-total').textContent(),
            ),
        ).toBe(String(pricePerPerson * 2));
    });

    test('un invite et ses accompagnateurs obtiennent une reservation', async ({
        page,
    }, testInfo) => {
        const { publicUrl, unit } = state();

        await register(page, {
            eventUrl: publicUrl,
            name: `Awa Traore ${testInfo.project.name}`,
            phone: phoneFor(testInfo, 4),
            unit,
            companions: ['Moussa Traore', 'Fatou Traore'],
        });

        // La reservation porte une reference de dossier et propose de deposer la preuve.
        await expect(page.getByTestId('registration-reference')).toBeVisible();
        await expect(page.getByTestId('proof-form')).toBeVisible();
    });

    test('un nom manquant est signale sans quitter le formulaire', async ({
        page,
    }) => {
        const { publicUrl } = state();

        await page.goto(publicUrl);
        await page.getByTestId('register-link').click();
        await page.getByTestId('registration-submit').click();

        // Le navigateur ou le serveur retient l'envoi : on reste sur le formulaire.
        await expect(page.getByTestId('registration-form')).toBeVisible();
        await expect(page.getByTestId('registration-countdown')).toBeHidden();
    });

    test('un evenement complet propose la liste d attente', async ({
        page,
    }, testInfo) => {
        const { fullEventUrl, unit } = state();

        await page.goto(fullEventUrl);

        await expect(page.getByTestId('register-link')).toBeHidden();
        await page.getByTestId('waitlist-link').click();

        await expect(page.getByTestId('waitlist-form')).toBeVisible();
        await page
            .getByTestId('waitlist-name')
            .fill(`Mariam Bamba ${testInfo.project.name}`);
        await page.locator('#phone').fill(phoneFor(testInfo, 5));
        await page.getByTestId('waitlist-unit').click();
        await page.getByRole('option', { name: unit, exact: true }).click();
        await page.getByTestId('waitlist-submit').click();

        // L'invite connait sa place dans la file.
        await expect(page.getByTestId('waitlist-position')).toBeVisible();
    });

    test('l invite passe la page en anglais et y reste', async ({ page }) => {
        const { publicUrl } = state();

        await page.goto(publicUrl);
        await expect(page.getByTestId('register-link')).toHaveText(
            "S'inscrire",
        );

        await page.getByTestId('locale-switcher-trigger').click();
        await page
            .getByTestId('locale-switcher-item')
            .filter({ hasText: 'English' })
            .click();

        await expect(page.getByTestId('register-link')).toHaveText('Register');

        // Le choix est garde : la page suivante est aussi en anglais.
        await page.reload();

        await expect(page.getByTestId('register-link')).toHaveText('Register');
    });
});
