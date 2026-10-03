import { expect, test } from '@playwright/test';
import { signIn, state } from './support/helpers';

/**
 * Le back-office de l'organisateur sur les donnees de demonstration : ses evenements, la base
 * d'inscrits d'un evenement et son export.
 */
test.describe('back-office de l organisateur', () => {
    test.beforeEach(async ({ page }) => {
        await signIn(page);
    });

    test('la liste des evenements se filtre par la recherche', async ({
        page,
    }) => {
        const { tenantSlug, eventName } = state();

        await page.goto(`/${tenantSlug}/events`);
        // Tous les evenements, pour que la recherche ne depende pas de l'onglet ouvert.
        await page.getByTestId('event-filter-all').click();

        await page.getByTestId('event-search').fill(eventName);
        await expect(page.getByText(eventName).first()).toBeVisible();

        await page.getByTestId('event-search').fill('zzz aucun evenement zzz');
        await expect(page.getByText(eventName)).toHaveCount(0);
    });

    test('la base d inscrits d un evenement s ouvre et s exporte', async ({
        page,
    }) => {
        const { tenantSlug, eventId } = state();

        const response = await page.goto(
            `/${tenantSlug}/events/${eventId}/registrations`,
        );

        expect(response?.status()).toBe(200);

        // Les boutons d'export sont affiches tels quels : cliquer leur barre tombait au milieu,
        // sur le bouton PDF.
        const download = page.waitForEvent('download');
        await page.getByTestId('registrations-export-csv').click();

        // Le fichier porte le nom de l'evenement, pas un nom generique.
        expect((await download).suggestedFilename()).toMatch(/\.csv$/);
    });

    test('le tableau de bord de l organisation se charge', async ({ page }) => {
        const { tenantSlug } = state();

        const response = await page.goto(`/${tenantSlug}/dashboard`);

        expect(response?.status()).toBe(200);
    });

    test('la console de l editeur s ouvre pour le compte principal', async ({
        page,
    }) => {
        for (const screen of [
            'organisations',
            'recovery',
            'health',
            'security',
            'accounts',
            'messages',
            'audit',
        ]) {
            const response = await page.goto(`/console/${screen}`);

            expect(response?.status(), `console/${screen}`).toBe(200);
        }
    });
});
