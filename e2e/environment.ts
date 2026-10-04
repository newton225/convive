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
    // Tout se joue dans la requete : pas de file a faire tourner.
    QUEUE_CONNECTION: 'sync',
    // Redis, comme en developpement : le cache par organisation de `stancl/tenancy` exige un
    // cache a etiquettes, ce que le cache sur fichiers n'est pas (« This cache store does not
    // support tagging », premiere execution). Des bases Redis a part (7 et 8) et un prefixe
    // propre : rien ne se melange avec le cache du developpement.
    CACHE_STORE: 'redis',
    CACHE_PREFIX: 'convive-e2e-',
    REDIS_DB: '7',
    REDIS_CACHE_DB: '8',
    SESSION_DRIVER: 'file',
    MAIL_MAILER: 'array',
    // Aucun service exterieur, quels que soient les identifiants du .env de developpement : pas de
    // vrai envoi WhatsApp ni SMS, et pas de captcha Cloudflare, dont le widget se charge depuis
    // Internet et retenait le formulaire (« verification anti-robot », premiere execution apres son
    // ajout). Le captcha a ses propres tests cote serveur (`BotCheckTest`).
    WHATSAPP_DRIVER: '',
    WHATSAPP_BUSINESS_NUMBER: '',
    WHATSAPP_WEBHOOK_VERIFY_TOKEN: '',
    WHATSAPP_META_APP_SECRET: '',
    SMS_DRIVER: '',
    TURNSTILE_SITE_KEY: '',
    TURNSTILE_SECRET_KEY: '',
    // Le compte de demonstration n'a pas de double authentification (CLAUDE.md, « Compte principal
    // de developpement ») : hors de l'environnement local, il faut le dire.
    CONVIVE_ENFORCE_TWO_FACTOR: 'false',
    CONVIVE_CONSOLE_OPERATORS: 'admin@convive.com',
    CONVIVE_TRIAL_ENABLED: 'true',
    // Chaque invite simule se presente avec sa propre adresse (`distinctGuestAddress`) : la machine
    // de test est donc traitee comme le proxy qui la transmet. Sans cela, tous les invites
    // partageaient l'adresse locale et les limites par adresse (5 inscriptions en 10 minutes) les
    // bloquaient, comme elles bloqueraient un vrai abus.
    TRUSTED_PROXIES: '127.0.0.1',
};

export const account = {
    email: 'admin@convive.com',
    password: 'password',
};

export type State = {
    tenantSlug: string;
    // Une organisation dont le compte « Lecture » de demonstration n'est pas membre.
    foreignTenantSlug: string;
    // Un evenement ouvert, avec des places et un compte de versement visible.
    eventId: number;
    eventName: string;
    publicUrl: string;
    pricePerPerson: number;
    // Un evenement complet, pour la liste d'attente.
    fullEventUrl: string;
    unit: string;
};
