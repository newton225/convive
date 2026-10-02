import path from 'node:path';

/**
 * L'environnement des tests de bout en bout : une application lancee a part, avec sa propre base
 * centrale, ses propres bases d'organisation et ses propres fichiers, sous `storage/e2e`. Rien de ce
 * que ces tests creent ne touche les donnees de developpement.
 *
 * Ces variables sont passees au processus PHP lui-meme (et non lues dans un fichier `.env`) : le
 * chemin de stockage doit etre connu avant meme que Laravel ne charge sa configuration.
 */
export const root = path.resolve(import.meta.dirname, '..');

export const storagePath = path.join(root, 'storage', 'e2e');

export const centralDatabase = path.join(storagePath, 'central.sqlite');

// Ce que la mise en place note pour les tests : l'adresse publique d'un evenement ouvert, etc.
export const statePath = path.join(storagePath, 'state.json');

export const port = 8010;

export const baseUrl = `http://localhost:${port}`;

export const environment: Record<string, string> = {
    APP_ENV: 'e2e',
    APP_DEBUG: 'true',
    APP_URL: baseUrl,
    LARAVEL_STORAGE_PATH: storagePath,
    DB_CENTRAL_DATABASE: centralDatabase,
    // Tout se joue dans la requete : pas de file a faire tourner, pas de Redis a demarrer.
    QUEUE_CONNECTION: 'sync',
    CACHE_STORE: 'file',
    SESSION_DRIVER: 'file',
    MAIL_MAILER: 'array',
    // Le compte de demonstration n'a pas de double authentification (CLAUDE.md, « Compte principal
    // de developpement ») : hors de l'environnement local, il faut le dire.
    CONVIVE_ENFORCE_TWO_FACTOR: 'false',
    CONVIVE_CONSOLE_OPERATORS: 'admin@convive.com',
    CONVIVE_TRIAL_ENABLED: 'true',
};

export const account = {
    email: 'admin@convive.com',
    password: 'password',
};

export type State = {
    tenantSlug: string;
    eventId: number;
    eventName: string;
    publicUrl: string;
    unit: string;
};
