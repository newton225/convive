import { execFileSync, spawn } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { centralDatabase, environment, root, statePath, storagePath } from '../e2e/environment.ts';

/**
 * L'application de test des campagnes QA : la meme que celle des tests de bout en bout (base et
 * fichiers sous `storage/e2e`, jamais ceux du developpement), preparee ici sans Playwright, et lancee
 * sur un ou plusieurs ports. Plusieurs ports = plusieurs processus PHP, pour mesurer la montee en
 * charge : le serveur integre de PHP traite une requete a la fois.
 */
export const qaEnvironment = (extra = {}) => ({ ...process.env, ...environment, ...extra });

export function prepareApplication() {
    const hot = path.join(root, 'public', 'hot');
    const manifest = path.join(root, 'public', 'build', 'manifest.json');

    if (!fs.existsSync(hot) && !fs.existsSync(manifest)) {
        throw new Error('Aucun script a servir : lancez `npm run build` avant la campagne.');
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

    const php = (args, extra = {}) =>
        execFileSync('php', args, {
            cwd: root,
            env: qaEnvironment(extra),
            encoding: 'utf8',
            maxBuffer: 64 * 1024 * 1024,
        });

    php(['artisan', 'cache:clear']);
    php(['artisan', 'migrate:fresh', '--seed', '--force']);
    fs.writeFileSync(statePath, php(['e2e/support/state.php']));

    return JSON.parse(fs.readFileSync(statePath, 'utf8'));
}

export async function startServer(port, extra = {}) {
    const env = qaEnvironment({
        APP_URL: `http://localhost:${port}`,
        ...extra,
    });

    const child = spawn(
        'php',
        [
            '-d',
            'variables_order=EGPCS',
            '-S',
            `127.0.0.1:${port}`,
            '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
        ],
        { cwd: path.join(root, 'public'), env, stdio: ['ignore', 'ignore', 'ignore'] },
    );

    for (let attempt = 0; attempt < 120; attempt++) {
        try {
            const response = await fetch(`http://127.0.0.1:${port}/up`);

            if (response.ok) {
                return child;
            }
        } catch {
            // Le serveur n'ecoute pas encore.
        }

        await new Promise((resolve) => setTimeout(resolve, 500));
    }

    child.kill();
    throw new Error(`Le serveur du port ${port} ne repond pas.`);
}

export function stopServers(children) {
    for (const child of children) {
        child.kill();
    }
}

export function runPhp(args, extra = {}) {
    return execFileSync('php', args, {
        cwd: root,
        env: qaEnvironment(extra),
        encoding: 'utf8',
        maxBuffer: 64 * 1024 * 1024,
    });
}
