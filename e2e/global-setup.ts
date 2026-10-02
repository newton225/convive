import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import {
    centralDatabase,
    environment,
    root,
    statePath,
    storagePath,
} from './environment';

/**
 * Prepare l'application de test avant le premier parcours : un dossier de stockage neuf, une base
 * centrale vide, les migrations, puis les donnees de demonstration (`DatabaseSeeder` : une
 * organisation, ses evenements, le compte `admin@convive.com`). Chaque execution repart de zero.
 */
export default function globalSetup(): void {
    // Les pages chargent leurs scripts soit depuis le serveur de developpement (`public/hot`), soit
    // depuis les fichiers construits. Sans l'un ni l'autre, tous les parcours echoueraient sur une
    // page blanche : autant le dire tout de suite.
    const hot = path.join(root, 'public', 'hot');
    const manifest = path.join(root, 'public', 'build', 'manifest.json');

    if (!fs.existsSync(hot) && !fs.existsSync(manifest)) {
        throw new Error(
            'Aucun script a servir : lancez `npm run build` (ou `npm run dev`) avant les tests de bout en bout.',
        );
    }

    fs.rmSync(storagePath, { recursive: true, force: true });

    for (const directory of [
        'app/public',
        'app/tenant-media',
        'app/payment-proofs',
        'framework/cache/data',
        'framework/sessions',
        'framework/views',
        'framework/testing',
        'logs',
    ]) {
        fs.mkdirSync(path.join(storagePath, directory), { recursive: true });
    }

    fs.writeFileSync(centralDatabase, '');

    const env = { ...process.env, ...environment };
    const php = (args: string[]) =>
        execFileSync('php', args, { cwd: root, env, encoding: 'utf8' });

    php(['artisan', 'migrate:fresh', '--seed', '--force']);

    fs.writeFileSync(statePath, php(['e2e/support/state.php']));
}
