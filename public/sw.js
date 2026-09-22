/*
 * Le service worker de Convive (PWA, CLAUDE.md « Pile imposee »).
 *
 * Trois regles, dans l'ordre :
 *  1. Rien d'autre qu'un GET de meme origine n'est jamais intercepte : les envois (preuves, scans)
 *     partent toujours sur le reseau, une file locale les rejoue cote page (voir `use-scan-queue`).
 *  2. Les visites Inertia (en-tete `X-Inertia`) et tout ce qui est personnel ne sont jamais mis en
 *     cache : seuls les fichiers du build (immuables, nommes par empreinte) et la coquille hors ligne.
 *  3. Deux parcours gardent leur page en secours pour fonctionner sans reseau : le parcours invite
 *     (`/e/...`, dont le billet) et l'ecran de scan d'un evenement. Reseau d'abord, copie ensuite.
 *
 * Toute autre navigation hors ligne affiche `/offline.html`.
 */

const VERSION = 'convive-v1';
const STATIC_CACHE = `${VERSION}-static`;
const PAGES_CACHE = `${VERSION}-pages`;
const OFFLINE_URL = '/offline.html';
const PRECACHE = [OFFLINE_URL, '/favicon.svg', '/manifest.webmanifest'];

const GUEST_PATH = /^\/e\//;
const SCAN_PATH = /^\/[^/]+\/events\/\d+\/scan$/;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => !key.startsWith(VERSION))
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

async function cacheFirst(request) {
    const cached = await caches.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok) {
        const cache = await caches.open(STATIC_CACHE);
        cache.put(request, response.clone());
    }

    return response;
}

async function networkFirstPage(request) {
    const cache = await caches.open(PAGES_CACHE);

    try {
        const response = await fetch(request);

        // Une redirection (connexion expiree, par exemple) ne se garde pas : la copie serait une
        // page de connexion servie a la place du billet.
        if (response.ok && !response.redirected) {
            cache.put(request, response.clone());
        }

        return response;
    } catch {
        return (await cache.match(request)) ?? (await caches.match(OFFLINE_URL));
    }
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.headers.get('X-Inertia')) {
        return;
    }

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(request));

        return;
    }

    if (request.mode !== 'navigate') {
        return;
    }

    if (GUEST_PATH.test(url.pathname) || SCAN_PATH.test(url.pathname)) {
        event.respondWith(networkFirstPage(request));

        return;
    }

    event.respondWith(
        fetch(request).catch(() => caches.match(OFFLINE_URL)),
    );
});
