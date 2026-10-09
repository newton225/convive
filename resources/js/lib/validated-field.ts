/**
 * Le champ a valider en temps reel (validation « precognitive » de Laravel) quand la personne quitte
 * un champ du formulaire, ou null quand ce n'est pas un champ a valider.
 *
 * Le nom du champ passe de la notation des formulaires (`table_groups[0][count]`) a celle des
 * regles de validation (`table_groups.0.count`). Les champs de fichier, les champs caches (les
 * valeurs des listes deroulantes) et les cases a cocher ne se valident pas ainsi : un fichier ne
 * part pas dans une requete de validation, et les autres n'ont pas de saisie a verifier.
 */
export function validatedFieldName(target: EventTarget): string | null {
    if (
        !(
            target instanceof HTMLInputElement ||
            target instanceof HTMLTextAreaElement ||
            target instanceof HTMLSelectElement
        )
    ) {
        return null;
    }

    if (
        target.name === '' ||
        (target instanceof HTMLInputElement &&
            ['file', 'hidden', 'checkbox', 'radio'].includes(target.type))
    ) {
        return null;
    }

    return target.name.replace(/\[([^\]]*)\]/g, '.$1');
}
