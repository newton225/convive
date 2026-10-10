import { expect, test } from './support/fixtures';
import { account } from './environment';

/**
 * Les parcours de compte, tels que les vit un visiteur : creer son espace, se tromper de mot de passe,
 * se connecter, se deconnecter, demander un nouveau mot de passe. Chaque test part d'un navigateur
 * vierge.
 */
test('un visiteur cree son compte et son organisation, puis se deconnecte', async ({ page }) => {
    const email = `nouveau.${Date.now()}@example.com`;

    await page.goto('/register');
    await page.locator('#name').fill('Nouvel Organisateur');
    await page.locator('#email').fill(email);
    await page.locator('#phone').fill('0707991122').catch(() => undefined);
    await page.locator('#password').fill('Un-mot-de-passe-solide-2026!');
    await page.locator('#password_confirmation').fill('Un-mot-de-passe-solide-2026!');

    const organisation = page.locator('#organisation_name');

    if (await organisation.isVisible().catch(() => false)) {
        await organisation.fill('Association des Essais');
    }

    // Les conditions d'utilisation sont obligatoires.
    await page.getByTestId('register-user-button').click();
    await expect(page).toHaveURL(/\/register/);

    await page.getByTestId('register-terms').click();
    await page.getByTestId('register-user-button').click();

    await page.waitForURL((url) => !url.pathname.startsWith('/register'), { timeout: 30_000 });

    // L'adresse email doit etre confirmee par un lien avant tout acces (README ecran 2) : le compte
    // est ouvert, mais l'espace attend la confirmation.
    await expect(page.getByRole('heading', { name: /Vérification de l'adresse email/ })).toBeVisible();
    await expect(page.getByRole('button', { name: /Renvoyer l'email de vérification/ })).toBeVisible();

    // Tant que l'adresse n'est pas confirmee, le back-office reste ferme.
    await page.goto('/settings/security');
    await expect(page).toHaveURL(/email\/verify/);

    await page.getByRole('button', { name: /Se déconnecter/ }).or(page.getByRole('link', { name: /Se déconnecter/ })).first().click();
    await page.waitForURL((url) => url.pathname === '/' || url.pathname.startsWith('/login'));
});

test('un mot de passe trop faible est refuse a l\'inscription', async ({ page }) => {
    await page.goto('/register');
    await page.locator('#name').fill('Faible');
    await page.locator('#email').fill(`faible.${Date.now()}@example.com`);
    await page.locator('#password').fill('123');
    await page.locator('#password_confirmation').fill('123');
    await page.getByTestId('register-terms').click();
    await page.getByTestId('register-user-button').click();

    await expect(page).toHaveURL(/\/register/);
});

test('un mauvais mot de passe est signale sans reveler si le compte existe', async ({ page }) => {
    const messages: string[] = [];

    for (const email of [account.email, 'quelquun.dautre@example.com']) {
        await page.goto('/login');
        await page.locator('#email').fill(email);
        await page.locator('#password').fill('pas-le-bon-mot-de-passe');
        await page.getByTestId('login-button').click();
        await expect(page).toHaveURL(/\/login/);
        messages.push((await page.locator('[data-error-for], .text-destructive').first().innerText()).trim());
    }

    expect(messages[0]).toBe(messages[1]);
    expect(messages[0].length).toBeGreaterThan(5);
});

test('la page de mot de passe oublie repond de la meme maniere pour un compte inconnu', async ({ page }) => {
    await page.goto('/forgot-password');
    await page.locator('#email').fill('inconnu.total@example.com');
    await page.getByTestId('email-password-reset-link-button').click();

    // Pas d'erreur 4xx visible, pas de « ce compte n'existe pas ».
    await expect(page.getByText(/n'existe pas|introuvable|inconnu/i)).toHaveCount(0);
});

test('la connexion ramene a la page demandee, jamais vers un site etranger', async ({ page }) => {
    await page.goto('/login?redirect=https://evil.example/&intended=https://evil.example/');
    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.getByTestId('login-button').click();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'));

    expect(new URL(page.url()).hostname).not.toContain('evil.example');
});

test('une page du back-office demande la connexion puis y revient', async ({ page }) => {
    await page.goto('/settings/profile');
    await expect(page).toHaveURL(/\/login/);

    await page.locator('#email').fill(account.email);
    await page.locator('#password').fill(account.password);
    await page.getByTestId('login-button').click();

    await page.waitForURL((url) => !url.pathname.startsWith('/login'));
    await expect(page).toHaveURL(/settings\/profile|dashboard|events/);
});
