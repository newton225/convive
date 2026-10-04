/**
 * Les regles d'un nouveau mot de passe, telles que le serveur les applique (`App\Support\PasswordPolicy`) :
 * l'indicateur coche les memes, rien de plus. La recherche dans les fuites connues ne se fait qu'a
 * l'envoi, par le serveur.
 */
export type PasswordPolicy = {
    min: number;
    mixedCase: boolean;
    numbers: boolean;
    symbols: boolean;
    uncompromised: boolean;
};

export type PasswordCheck = {
    key: 'min' | 'mixedCase' | 'numbers' | 'symbols';
    met: boolean;
};

export function passwordChecks(
    password: string,
    policy: PasswordPolicy,
): PasswordCheck[] {
    const checks: PasswordCheck[] = [
        // Compte en points de code, comme `mb_strlen` cote serveur : une lettre accentuee vaut un.
        { key: 'min', met: Array.from(password).length >= policy.min },
    ];

    if (policy.mixedCase) {
        checks.push({
            key: 'mixedCase',
            met: /\p{Lu}/u.test(password) && /\p{Ll}/u.test(password),
        });
    }

    if (policy.numbers) {
        checks.push({ key: 'numbers', met: /\p{N}/u.test(password) });
    }

    if (policy.symbols) {
        checks.push({
            key: 'symbols',
            met: /[\p{Z}\p{S}\p{P}]/u.test(password),
        });
    }

    return checks;
}

/**
 * The share of rules met, from 0 to 1 : la barre se remplit a mesure.
 */
export function passwordScore(checks: PasswordCheck[]): number {
    return checks.length === 0
        ? 1
        : checks.filter((check) => check.met).length / checks.length;
}
