import { expect, test } from '@playwright/test';
import { signIn, state } from './support/helpers';

/**
 * Le cloisonnement vu du navigateur (CLAUDE.md, « Multi-locataire » et « Profils et permissions ») :
 * une organisation dont on n'est pas membre n'existe pas (404), et un profil ne voit pas ce que ses
 * permissions ne couvrent pas. Le compte « Lecture » de demonstration sert de temoin.
 */
test.describe('cloisonnement des acces', () => {
    const reader = 'lecture@convive.com';

    test('un membre ouvre les evenements de son organisation', async ({
        page,
    }) => {
        const { tenantSlug, eventName } = state();

        await signIn(page, reader);

        const response = await page.goto(`/${tenantSlug}/events`);

        expect(response?.status()).toBe(200);
        await expect(page.getByText(eventName).first()).toBeVisible();
    });

    test('une organisation dont on n est pas membre repond introuvable', async ({
        page,
    }) => {
        const { foreignTenantSlug } = state();

        await signIn(page, reader);

        const response = await page.goto(`/${foreignTenantSlug}/events`);

        // Introuvable, jamais « interdit » : rien ne doit dire que l'organisation existe.
        expect(response?.status()).toBe(404);
    });

    test('une organisation qui n existe pas repond de la meme facon', async ({
        page,
    }) => {
        await signIn(page, reader);

        const response = await page.goto(
            '/organisation-qui-n-existe-pas/events',
        );

        expect(response?.status()).toBe(404);
    });

    test('le profil Lecture ne gere pas l acces du support', async ({
        page,
    }) => {
        const { tenantSlug } = state();

        await signIn(page, reader);

        // Reserve aux Proprietaires.
        const response = await page.goto(
            `/settings/tenants/${tenantSlug}/support-access`,
        );

        expect(response?.status()).toBe(403);
    });

    test('la console de l editeur n existe pas pour un membre d organisation', async ({
        page,
    }) => {
        await signIn(page, reader);

        const response = await page.goto('/console/organisations');

        expect(response?.status()).toBe(404);
    });

    test('un evenement d un lien public inconnu repond introuvable', async ({
        page,
    }) => {
        const { publicUrl } = state();
        // Meme sous-domaine, jeton qui n'existe pas.
        const unknown = publicUrl.replace(/\/e\/.+$/, `/e/${'a'.repeat(64)}`);

        const response = await page.goto(unknown);

        expect(response?.status()).toBe(404);
    });
});
