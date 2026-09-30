import type { LocalScanKind } from '@/components/scan/offline-scan-panel';
import type { ScanResultValue } from '@/types';

export type ScanVerdictTone = 'success' | 'warning' | 'danger';

export type ScanVerdict = {
    tone: ScanVerdictTone;
    // Cle de traduction du libelle, affiche avec une icone : jamais la couleur seule.
    labelKey: string;
};

/**
 * Le verdict d'un scan en ligne, tel que le serveur l'a rendu (README 2.8) : entree validee,
 * billet deja scanne (a regarder de pres, le forcage reste possible) ou billet refuse.
 */
export function onlineVerdict(result: ScanResultValue): ScanVerdict {
    switch (result) {
        case 'accepted':
            return { tone: 'success', labelKey: 'scan.results.accepted' };
        case 'already_scanned':
            return {
                tone: 'warning',
                labelKey: 'scan.results.already_scanned',
            };
        case 'refused':
            return { tone: 'danger', labelKey: 'scan.results.refused' };
    }
}

/**
 * Le verdict d'un scan hors ligne, verifie sur l'appareil avec la cle publique (SECURITY.md C2).
 */
export function offlineVerdict(kind: LocalScanKind): ScanVerdict {
    switch (kind) {
        case 'verified':
            return {
                tone: 'success',
                labelKey: 'offline.scan.verified_offline',
            };
        case 'already_local':
            return { tone: 'warning', labelKey: 'offline.scan.already_local' };
        default:
            return { tone: 'danger', labelKey: `offline.scan.${kind}` };
    }
}

// Retour haptique : l'agent regarde l'invite, pas son ecran. Motifs distincts par verdict.
const Vibrations: Record<ScanVerdictTone, number[]> = {
    success: [80],
    warning: [80, 60, 80],
    danger: [250],
};

export function vibrateFor(tone: ScanVerdictTone): void {
    if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
        navigator.vibrate(Vibrations[tone]);
    }
}
