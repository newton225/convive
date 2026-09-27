/**
 * Couleur stable associee a une unite, pour le regroupement visuel du plan de salle (README
 * ecran 21) : la meme unite porte la meme couleur d'une table a l'autre, ce qui permet de la
 * reperer au premier coup d'oeil dans la grille entiere, pas seulement au sein d'une table.
 *
 * Reutilise la palette de graphiques deja definie dans la charte (`--chart-1` a `--chart-5`,
 * CLAUDE.md « Design et experience utilisateur ») plutot que d'inventer une seconde palette : au
 * dela de cinq unites, la couleur se repete, ce qui reste sans consequence puisqu'un nom d'unite
 * identique produit toujours la meme couleur.
 */
const PALETTE_SIZE = 5;

function hashToIndex(value: string): number {
    let hash = 0;

    for (let i = 0; i < value.length; i++) {
        hash = (hash * 31 + value.charCodeAt(i)) | 0;
    }

    return Math.abs(hash) % PALETTE_SIZE;
}

export function unitColorClass(unitName: string): string {
    return `bg-chart-${hashToIndex(unitName) + 1}`;
}
