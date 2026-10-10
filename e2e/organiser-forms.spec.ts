import { expect, test } from './support/fixtures';
import { signIn, state } from './support/helpers';

/**
 * Le formulaire d'evenement face a des saisies incorrectes : les erreurs doivent apparaitre au bon
 * endroit, sans attendre l'envoi quand c'est possible (validation en temps reel, Precognition), et
 * disparaitre des que la valeur est corrigee.
 */
test.beforeEach(async ({ page }) => {
    await signIn(page);
    await page.goto(`/${state().tenantSlug}/events/create`);
    await page.getByTestId('event-name').fill('Evenement de validation');
});

test('une fin avant le debut est signalee a la sortie du champ, puis disparait une fois corrigee', async ({ page }) => {
    await page.getByTestId('event-starts_at').fill('2027-06-12T19:00');
    await page.getByTestId('event-ends_at').fill('2027-06-12T18:00');
    // Sortir du champ declenche la validation du serveur, sans enregistrer.
    await page.getByTestId('event-venue').focus();

    const message = page.getByText('La date de fin doit être après la date de début.');
    await expect(message).toBeVisible();

    await page.getByTestId('event-ends_at').fill('2027-06-12T22:00');
    // La saisie efface l'erreur tout de suite, sans attendre le serveur.
    await expect(message).toBeHidden();

    await page.getByTestId('event-venue').focus();
    await expect(message).toBeHidden();
});

test('une fin sans debut est refusee avec un message qui nomme la date de debut', async ({ page }) => {
    await page.getByTestId('event-ends_at').fill('2027-06-12T22:00');
    await page.getByTestId('event-venue').focus();

    await expect(page.getByText('Indiquez d’abord la date de début pour donner une date de fin.')).toBeVisible();
});

test('le quota de chaque tarif est obligatoire', async ({ page }) => {
    const row = page.getByTestId('price-category-row').first();

    await row.getByTestId('price-category-name').fill('Standard');
    await row.getByTestId('price-category-price').fill('5000');
    await row.getByTestId('price-category-quota').fill('');

    await page.getByTestId('event-submit').click();

    // Le navigateur bloque l'envoi d'un champ obligatoire vide : on reste sur le formulaire de creation.
    await expect(page).toHaveURL(/\/events\/create/);
    expect(await row.getByTestId('price-category-quota').evaluate((input: HTMLInputElement) => input.validity.valueMissing)).toBe(true);
});

test('la somme des quotas ne peut pas depasser la salle : avertissement, puis refus du serveur', async ({ page }) => {
    await page.getByTestId('event-starts_at').fill('2027-06-12T19:00');
    await page.getByTestId('event-table-group-count').first().fill('5');
    await page.getByTestId('event-table-group-seats').first().fill('8');

    const rows = page.getByTestId('price-category-row');
    await rows.first().getByTestId('price-category-name').fill('Standard');
    await rows.first().getByTestId('price-category-price').fill('10000');
    await rows.first().getByTestId('price-category-quota').fill('30');
    await page.getByTestId('price-category-add').click();
    await rows.nth(1).getByTestId('price-category-name').fill('Gratuit');
    await rows.nth(1).getByTestId('price-category-price').fill('0');
    await rows.nth(1).getByTestId('price-category-quota').fill('25');

    // L'avertissement en direct : 55 places promises pour 40.
    await expect(page.getByText(/quotas additionnés \(55\)/)).toBeVisible();

    await page.getByTestId('event-payment-account').first().click();
    await page.getByTestId('event-submit').click();

    // Le serveur refuse : on reste sur le formulaire, et le message ne se double pas.
    await expect(page).toHaveURL(/\/events\/create/);
    await expect(page.getByText(/quotas additionnés \(55\)/)).toBeVisible();

    // Corriger le quota fait disparaitre le message sans renvoyer le formulaire.
    await rows.nth(1).getByTestId('price-category-quota').fill('10');
    await expect(page.getByText(/quotas additionnés/)).toBeHidden();
});

test('une date de debut passee est acceptee sur un brouillon sans inscription', async ({ page }) => {
    await page.getByTestId('event-starts_at').fill('2020-01-01T19:00');
    await page.getByTestId('event-venue').focus();

    await expect(page.getByText(/ne peut pas être dans le passé/)).toBeHidden();
});
