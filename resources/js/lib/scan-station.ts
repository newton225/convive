/**
 * Le poste de controle de cet appareil (« Entree principale »), memorise d'une session a l'autre :
 * c'est un reglage du telephone, pas une donnee de l'evenement. Hors du prefixe `convive:scan-`
 * que la deconnexion efface (SECURITY.md M8) : il ne contient ni nom ni numero de table.
 */
const stationKey = 'convive:station';

export function readStation(): string {
    try {
        return localStorage.getItem(stationKey) ?? '';
    } catch {
        return '';
    }
}

export function writeStation(station: string): void {
    try {
        localStorage.setItem(stationKey, station);
    } catch {
        // Stockage indisponible : le poste sera a ressaisir au prochain chargement.
    }
}
