import { expect, test } from '@playwright/test';
import { signIn, state } from './support/helpers';

/**
 * Le site et l'entree dans le back-office : ce qu'un visiteur puis un organisateur voient d'abord.
 */
test.describe('site et connexion', () => {
    test("l'accueil se charge et mene aux pages juridiques", async ({
        page,
    }) => {
        await page.goto('/');

        await expect(page.locator('h1').first()).toBeVisible();

        await page.goto('/confidentialite');
        await expect(page.getByRole('heading', { level: 1 })).toContainText(
            'Politique de confidentialité',
        );

        await page.goto('/conditions');
        await expect(page.getByRole('heading', { level: 1 })).toContainText(
            'Conditions générales',
        );
    });

    test('un compte inconnu ne se connecte pas', async ({ page }) => {
        await page.goto('/login');
        await page.locator('#email').fill('inconnu@convive.test');
        await page.locator('#password').fill('mauvais-mot-de-passe');
        await page.getByTestId('login-button').click();

        await expect(page).toHaveURL(/\/login/);
    });

    test("l'organisateur se connecte et retrouve ses evenements", async ({
        page,
    }) => {
        const { tenantSlug, eventName } = state();

        await signIn(page);
        await page.goto(`/${tenantSlug}/events`);

        await expect(page.getByText(eventName).first()).toBeVisible();
    });

    test("la console n'existe pas pour un visiteur", async ({ page }) => {
        const response = await page.goto('/console/organisations');

        // Un visiteur est renvoye a la connexion, sans rien apprendre de la console.
        expect(response?.url()).toContain('/login');
    });
});
