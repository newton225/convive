/**
 * Les reperes de mouvement de la vitrine (CLAUDE.md, « Animation et site produit ») : une courbe
 * douce qui freine a l'arrivee, et des durees entre 0.2 et 0.6 s. Partages pour que toutes les
 * sections bougent de la meme facon.
 */
export const EaseOut = [0.22, 1, 0.36, 1] as const;

export const Duration = {
    quick: 0.25,
    base: 0.5,
    slow: 0.6,
} as const;
