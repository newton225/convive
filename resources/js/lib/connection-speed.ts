/** Octets lus pour juger la connexion : les premiers de la video elle-meme, aucun fichier de test a part. */
export const probeBytes = 256 * 1024;

/** Debit minimal (megabits par seconde) au-dela duquel la video se lance seule, sans son. */
export const fastEnoughMbps = 4;

/** Au-dela, le test est abandonne : la connexion est trop lente pour lancer quoi que ce soit. */
const probeTimeoutMs = 3000;

export function megabitsPerSecond(bytes: number, milliseconds: number): number {
    if (milliseconds <= 0) {
        return 0;
    }

    return (bytes * 8) / 1_000_000 / (milliseconds / 1000);
}

export function isFastEnough(bytes: number, milliseconds: number): boolean {
    return megabitsPerSecond(bytes, milliseconds) >= fastEnoughMbps;
}

type NetworkInformation = { saveData?: boolean };

/**
 * Dit si la connexion permet de lancer la video sans souci pour les donnees de l'invite. Aucune API ne
 * distingue le Wi-Fi sur tous les navigateurs (absente de Safari et Firefox) : on mesure donc le debit reel
 * en lisant le debut de la video. L'economiseur de donnees, quand le navigateur le signale, l'emporte
 * toujours. Toute erreur, ou un test trop long, vaut refus : dans le doute, rien ne se lance.
 */
export async function hasFastConnection(url: string): Promise<boolean> {
    const connection = (
        navigator as Navigator & { connection?: NetworkInformation }
    ).connection;

    if (connection?.saveData === true) {
        return false;
    }

    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), probeTimeoutMs);

    try {
        const startedAt = performance.now();
        const response = await fetch(url, {
            headers: { Range: `bytes=0-${probeBytes - 1}` },
            signal: controller.signal,
        });

        if (!response.ok) {
            return false;
        }

        const received = (await response.arrayBuffer()).byteLength;

        return isFastEnough(received, performance.now() - startedAt);
    } catch {
        return false;
    } finally {
        window.clearTimeout(timer);
    }
}
