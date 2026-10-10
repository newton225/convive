import { expect, test as base } from '@playwright/test';

/**
 * Un `test` qui echoue des qu'une page leve une erreur JavaScript. Une page blanche vient presque
 * toujours d'une exception dans un composant (incident du 2026-10-09 : le formulaire de reclamation
 * lisait une donnee absente et la page du dossier restait vide) : les verifications visibles ne la
 * voient pas toujours, l'erreur de la console, si.
 *
 * Les erreurs de ressource attendues (404 d'une page inconnue, 419, 422, 429 d'un test de limite) et les
 * avertissements de CSP sur un style vide (voir le rapport de tests) ne comptent pas.
 */
const ignorable = [
    /Failed to load resource: the server responded with a status of (401|403|404|419|422|429)/,
    /Refused to (apply a stylesheet|apply inline style|connect to (')?ws:)/,
    /Applying inline style violates the following Content Security Policy directive/,
    /favicon/,
    /\[vite\]/,
    // Le rechargement a chaud de Vite (developpement seulement) : la CSP de l'application le refuse.
    /Connecting to 'ws:\/\/127\.0\.0\.1:\d+\/.*Content Security Policy/,
    /Download the React DevTools/,
];

export const test = base.extend<{ pageErrors: string[] }>({
    pageErrors: [
        async ({ page }, use) => {
            const errors: string[] = [];

            page.on('pageerror', (error) =>
                errors.push(`pageerror: ${error.message}`),
            );
            page.on('console', (message) => {
                if (
                    message.type() === 'error' &&
                    !ignorable.some((pattern) => pattern.test(message.text()))
                ) {
                    errors.push(
                        `console.error: ${message.text().slice(0, 300)}`,
                    );
                }
            });

            await use(errors);

            expect(
                errors,
                'erreurs JavaScript levees pendant le parcours',
            ).toEqual([]);
        },
        { auto: true },
    ],
});

export { expect };
