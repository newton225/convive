import fs from 'node:fs';
import path from 'node:path';
import { root } from '../e2e/environment.ts';
import { prepareApplication, runPhp, startServer, stopServers } from './e2e-server.mjs';
import { percentile, Session } from './http.mjs';

/**
 * Performance et montee en charge, sur l'application de test avec un volume realiste (milliers
 * d'inscriptions). Des utilisateurs virtuels enchainent des requetes sans pause pendant une duree
 * donnee ; on releve debit, latences (p50, p95, p99) et erreurs, a plusieurs niveaux de concurrence.
 *
 * Le serveur integre de PHP traite UNE requete a la fois : on mesure donc deux configurations,
 * 1 puis 4 processus PHP (4 ports, charge repartie), pour montrer comment le debit suit le nombre
 * de processus applicatifs, comme derriere un PHP-FPM. Les chiffres absolus sont ceux d'un poste de
 * developpement sous Windows, avec SQLite : ils servent a comparer, pas a promettre.
 *
 * Usage : node qa/load.mjs [nombre d'inscriptions] [secondes par palier]
 */
const registrations = Number(process.argv[2] ?? 3000);
const seconds = Number(process.argv[3] ?? 8);
const levels = [1, 5, 10, 25, 50];
const basePort = 8030;

let counter = 0;
const uniqueAddress = () => {
    counter += 1;

    return `10.${(counter >> 16) & 255}.${(counter >> 8) & 255}.${(counter & 255) || 1}`;
};

function scenarios(facts, volume) {
    const publicHost = (port) => `${facts.subdomain}.localhost:${port}`;
    const json = { 'X-Inertia': 'true', Accept: 'application/json' };

    return [
        { name: 'Accueil du site', run: (s) => s.get('/', { headers: { 'X-Forwarded-For': uniqueAddress() } }) },
        { name: 'Page de connexion', run: (s) => s.get('/login', { headers: { 'X-Forwarded-For': uniqueAddress() } }) },
        {
            name: 'Lien public de l\'evenement',
            run: (s, port) => s.get(`/e/${facts.token}`, { host: publicHost(port), headers: { 'X-Forwarded-For': uniqueAddress() } }),
        },
        {
            name: 'Lien public (evenement de volume)',
            run: (s, port) => s.get(`/e/${volume.token}`, { host: publicHost(port), headers: { 'X-Forwarded-For': uniqueAddress() } }),
        },
        { name: 'Back-office : liste des evenements', auth: true, run: (s) => s.get(`/${facts.tenantSlug}/events`, { headers: json }) },
        { name: 'Back-office : tableau de bord', auth: true, run: (s) => s.get(`/${facts.tenantSlug}/dashboard`, { headers: json }) },
        {
            name: `Back-office : base d'inscrits (${volume.registrations} lignes, page 1)`,
            auth: true,
            run: (s) => s.get(`/${facts.tenantSlug}/events/${volume.eventId}/registrations`, { headers: json }),
        },
        {
            name: 'Back-office : base d\'inscrits, recherche',
            auth: true,
            run: (s) => s.get(`/${facts.tenantSlug}/events/${volume.eventId}/registrations?filter[search]=martin`, { headers: json }),
        },
        {
            name: 'Back-office : base d\'inscrits, derniere page',
            auth: true,
            run: (s) => s.get(`/${facts.tenantSlug}/events/${volume.eventId}/registrations?page=9999`, { headers: json }),
        },
        {
            name: 'Back-office : file des preuves',
            auth: true,
            run: (s) => s.get(`/${facts.tenantSlug}/events/${volume.eventId}/proofs`, { headers: json }),
        },
        {
            name: 'Back-office : rapport de l\'evenement',
            auth: true,
            run: (s) => s.get(`/${facts.tenantSlug}/events/${volume.eventId}/report`, { headers: json }),
        },
        {
            name: 'Inscription d\'un invite (ecriture)',
            write: true,
            run: async (s, port) => {
                const address = uniqueAddress();
                counter += 1;
                const response = await s.postWithCsrf(
                    `/e/${facts.token}/register`,
                    {
                        name: `Charge ${counter}`,
                        phone: `07${String(10000000 + counter).slice(-8)}`,
                        unit_id: facts.units[0].id,
                        price_category_id: facts.priceCategories[0].id,
                    },
                    { host: publicHost(port), headers: { 'X-Forwarded-For': address } },
                );

                return response;
            },
        },
    ];
}

async function measure(scenario, concurrency, ports, ownerCookies, facts) {
    const latencies = [];
    const statuses = new Map();
    let errors = 0;
    const deadline = performance.now() + seconds * 1000;
    const started = performance.now();

    const worker = async (index) => {
        const port = ports[index % ports.length];
        const session = new Session(`http://127.0.0.1:${port}`);

        if (scenario.auth) {
            for (const [name, value] of Object.entries(ownerCookies)) {
                session.cookies.set(name, value);
            }
        }

        if (scenario.write) {
            await session.get(`/e/${facts.token}/register`, { host: `${facts.subdomain}.localhost:${port}`, headers: { 'X-Forwarded-For': uniqueAddress() } });
        }

        while (performance.now() < deadline) {
            try {
                const response = await scenario.run(session, port);
                latencies.push(response.ms);
                statuses.set(response.status, (statuses.get(response.status) ?? 0) + 1);

                if (response.status >= 500) {
                    errors += 1;
                }
            } catch {
                errors += 1;
            }
        }
    };

    await Promise.all(Array.from({ length: concurrency }, (_, index) => worker(index)));

    const elapsed = (performance.now() - started) / 1000;
    const sorted = [...latencies].sort((a, b) => a - b);

    return {
        scenario: scenario.name,
        concurrency,
        requests: latencies.length,
        rps: Number((latencies.length / elapsed).toFixed(1)),
        p50: Math.round(percentile(sorted, 50)),
        p95: Math.round(percentile(sorted, 95)),
        p99: Math.round(percentile(sorted, 99)),
        max: Math.round(sorted.at(-1) ?? 0),
        errors,
        statuses: Object.fromEntries(statuses),
    };
}

async function run(processes, facts, volume) {
    const ports = Array.from({ length: processes }, (_, index) => basePort + index);
    const servers = [];

    for (const port of ports) {
        servers.push(await startServer(port, { TRUSTED_PROXIES: '127.0.0.1', CONVIVE_TRIAL_ENABLED: 'false' }));
    }

    const rows = [];

    try {
        const owner = new Session(`http://127.0.0.1:${ports[0]}`);
        await owner.login('admin@convive.com');
        const ownerCookies = Object.fromEntries(owner.cookies);

        for (const scenario of scenarios(facts, volume)) {
            for (const concurrency of levels) {
                // Une ecriture ne se rejoue pas a l'infini : on s'arrete aux places.
                if (scenario.write && concurrency > 25) {
                    continue;
                }

                const row = await measure(scenario, concurrency, ports, ownerCookies, facts);
                rows.push({ processes, ...row });
                console.log(`[${processes} proc] ${row.scenario} @${row.concurrency}: ${row.rps} req/s, p50 ${row.p50} ms, p95 ${row.p95} ms, p99 ${row.p99} ms, erreurs ${row.errors}`);
            }
        }
    } finally {
        stopServers(servers);
    }

    return rows;
}

const state = prepareApplication();
const facts = { ...state, ...JSON.parse(runPhp(['qa/facts.php'])) };
const started = performance.now();
const volume = JSON.parse(runPhp(['qa/seed-volume.php', String(registrations)]));
console.log(`Volume pose : ${volume.registrations} inscriptions en ${((performance.now() - started) / 1000).toFixed(0)} s`);

const all = [...(await run(1, facts, volume)), ...(await run(4, facts, volume))];

fs.mkdirSync(path.join(root, 'qa', 'results'), { recursive: true });
fs.writeFileSync(
    path.join(root, 'qa', 'results', 'load.json'),
    JSON.stringify({ date: new Date().toISOString(), registrations, secondsPerLevel: seconds, rows: all }, null, 2),
);
console.log('\nResultats ecrits dans qa/results/load.json');
