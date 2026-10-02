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

/**
 * Dit si un texte contient la saisie, sans tenir compte des accents ni de la casse. Une saisie vide
 * correspond a tout : rien n'a ete demande.
 */
export function matchesSearch(text: string, query: string): boolean {
    return normalizeForSearch(text).includes(normalizeForSearch(query));
}
