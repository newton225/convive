// Meme regle que `App\Rules\PersonName` et `PhoneNumber::normalizeIvorian` : le champ aide a la saisie,
// le serveur revalide quoi qu'il arrive.

export const IvorianNationalLength = 10;

/**
 * Retire d'un nom tout ce qui n'est pas une lettre (accents compris), un espace, un tiret ou une apostrophe.
 */
export function sanitizePersonName(value: string): string {
    return value.replace(/[^\p{L}\p{M}'’ -]/gu, '');
}

/**
 * Ramene une saisie ou un collage aux dix chiffres ivoiriens : chiffres seuls, indicatif `225` ou `00225`
 * retire quand le numero est colle en entier, jamais plus de dix chiffres.
 */
export function ivorianNationalDigits(value: string): string {
    let digits = value.replace(/\D+/g, '');

    if (digits.startsWith('00225') && digits.length > 10) {
        digits = digits.slice(5);
    } else if (digits.startsWith('225') && digits.length >= 13) {
        digits = digits.slice(3);
    }

    return digits.slice(0, IvorianNationalLength);
}

/**
 * Les chiffres par paires, pour la lecture : `0707123456` devient `07 07 12 34 56`.
 */
export function formatIvorianDigits(digits: string): string {
    return digits.replace(/(\d{2})(?=\d)/g, '$1 ');
}
