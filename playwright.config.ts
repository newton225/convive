import { defineConfig, devices } from '@playwright/test';
import { baseUrl, environment, port } from './e2e/environment';

/**
 * Tests de bout en bout (CLAUDE.md, « Conventions de code » : « tests de bout en bout sur les
 * parcours critiques »). Playwright pilote un vrai navigateur contre une application lancee a part,
 * sur ses propres bases (`e2e/environment.ts`), jamais contre les donnees de developpement.
 *
 * Deux navigateurs : Chrome de bureau pour le back-office, Safari d'iPhone pour le parcours de
 * l'invite, qui se fait au telephone.
 *
 * A lancer par `npm run test:e2e`, apres `npx playwright install chromium webkit` la premiere fois.
 */
export default defineConfig({
    testDir: './e2e',
    testMatch: '**/*.spec.ts',
    globalSetup: './e2e/global-setup.ts',
    // Les parcours partagent une meme base : ils se suivent, ils ne se croisent pas.
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 60_000,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL: baseUrl,
        locale: 'fr-FR',
        // L'application marque ses elements par `data-test`.
        testIdAttribute: 'data-test',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'bureau',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'iphone',
            use: { ...devices['iPhone 14'] },
            // Le back-office se teste au bureau ; l'iPhone rejoue les parcours de l'invite.
            testMatch: '**/guest-*.spec.ts',
        },
    ],
    webServer: {
        // Le serveur integre de PHP, lance directement : `artisan serve` retire une partie des
        // variables d'environnement avant de le demarrer, dont celles qui isolent cet environnement.
        // Lance depuis `public` : le routeur de Laravel cherche `index.php` dans le dossier courant
        // (premiere execution : « Failed opening required .../index.php »).
        // `variables_order=EGPCS` : sans lui, le serveur integre ne remet pas les variables du
        // processus dans `$_ENV`, et Laravel ignore `LARAVEL_STORAGE_PATH` pour les pages servies
        // (premiere execution : les bases d'organisation cherchees dans le stockage du developpement).
        command: `php -d variables_order=EGPCS -S 127.0.0.1:${port} ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`,
        cwd: 'public',
        url: `${baseUrl}/up`,
        env: environment,
        reuseExistingServer: false,
        timeout: 60_000,
    },
});
