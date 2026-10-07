export type PageWindowItem = number | 'gap';

/**
 * Les numeros de page a afficher (decision du proprietaire du projet, 2026-10-07) : la premiere et
 * la derniere page, la page courante et ses deux voisines, une ellipse pour le reste. Une ellipse
 * qui ne cacherait qu'une page est remplacee par cette page.
 */
export function pageWindow(current: number, last: number): PageWindowItem[] {
    const lastPage = Math.max(1, last);
    const page = Math.min(Math.max(1, current), lastPage);

    const shown = [1, page - 1, page, page + 1, lastPage]
        .filter((number) => number >= 1 && number <= lastPage)
        .filter((number, index, all) => all.indexOf(number) === index)
        .sort((a, b) => a - b);

    const items: PageWindowItem[] = [];

    shown.forEach((number, index) => {
        const previous = shown[index - 1];

        if (previous !== undefined && number - previous === 2) {
            items.push(previous + 1);
        } else if (previous !== undefined && number - previous > 2) {
            items.push('gap');
        }

        items.push(number);
    });

    return items;
}
