import { spawn } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { root } from '../e2e/environment.ts';
import { prepareApplication, qaEnvironment, runPhp } from './e2e-server.mjs';

/**
 * Test de concurrence reel : des dizaines de PROCESSUS PHP tentent en meme temps de prendre la
 * derniere place d'un evenement, sur la meme base SQLite et le meme cache Redis (verrou
 * `Cache::lock`). Le serveur integre de PHP ne traite qu'une requete a la fois : une salve de
 * requetes HTTP ne prouverait rien. Des processus concurrents, eux, sont de vrais serveurs
 * d'application qui se disputent la base.
 *
 * Scenarios : 1 place libre contre 20, 50 et 100 candidats ; 5 places libres contre 50 candidats.
 */
const scenarios = [
    { free: 1, candidates: 20 },
    { free: 1, candidates: 50 },
    { free: 1, candidates: 100 },
    { free: 5, candidates: 50 },
];

function runCandidate(number, facts) {
    return new Promise((resolve) => {
        const child = spawn('php', ['qa/race-hold.php', String(number), String(facts.eventId), String(facts.unitId), String(facts.categoryId)], {
            cwd: root,
            env: qaEnvironment(),
        });
        let output = '';
        child.stdout.on('data', (chunk) => (output += chunk));
        child.on('close', () => {
            try {
                resolve(JSON.parse(output.trim().split('\n').pop()));
            } catch {
                resolve({ number, held: false, error: `sortie illisible: ${output.slice(0, 120)}`, ms: 0 });
            }
        });
    });
}

const report = [];
let offset = 0;

for (const scenario of scenarios) {
    prepareApplication();
    const facts = JSON.parse(runPhp(['qa/race-prepare.php', String(scenario.free)]));
    const started = performance.now();

    const outcomes = await Promise.all(
        Array.from({ length: scenario.candidates }, (_, index) => runCandidate(offset + index + 1, facts)),
    );

    offset += scenario.candidates;
    const seconds = (performance.now() - started) / 1000;
    const verify = JSON.parse(runPhp(['qa/race-verify.php', String(facts.eventId)]));
    const held = outcomes.filter((outcome) => outcome.held).length;
    const refused = outcomes.filter((outcome) => !outcome.held && !outcome.error).length;
    const errors = outcomes.filter((outcome) => outcome.error);
    const latencies = outcomes.map((outcome) => outcome.ms).sort((a, b) => a - b);

    const entry = {
        ...scenario,
        capacity: verify.capacity,
        occupiedAfter: verify.occupied,
        held,
        refused,
        errors: errors.length,
        errorSamples: [...new Set(errors.map((outcome) => outcome.error))].slice(0, 3),
        oversold: verify.occupied > verify.capacity,
        exactlyAsManyAsFree: held === scenario.free,
        distinctSequences: verify.holdSequences === verify.distinctHoldSequences,
        seconds: Number(seconds.toFixed(1)),
        p50: latencies[Math.floor(latencies.length / 2)],
        p95: latencies[Math.floor(latencies.length * 0.95)],
    };

    report.push(entry);
    console.log(JSON.stringify(entry));
}

fs.mkdirSync(path.join(root, 'qa', 'results'), { recursive: true });
fs.writeFileSync(path.join(root, 'qa', 'results', 'race.json'), JSON.stringify({ date: new Date().toISOString(), report }, null, 2));

const failed = report.filter((entry) => entry.oversold || entry.held > entry.free || !entry.distinctSequences);
console.log(failed.length === 0 ? '\nAucune survente.' : `\nSURVENTE DETECTEE dans ${failed.length} scenario(s).`);
process.exit(failed.length === 0 ? 0 : 1);
