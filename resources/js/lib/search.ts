/**
 * Ramene un texte a une forme comparable pour une recherche : minuscules, sans accents. Taper
 * « kouame » doit trouver « Kouamé », et « etat major » doit trouver « ÉTAT MAJOR ».
 */
export function normalizeForSearch(value: string): string {
    return value
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase()
        .trim();
}
