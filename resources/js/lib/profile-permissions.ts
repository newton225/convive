import type { PermissionDomain, PermissionOption } from '@/types';

/**
 * Regles de cochage de l'editeur de profils. Le serveur fournit la dependance de chaque action
 * (`requires`, voir `TenantPermission::prerequisite()`) ; ce module ne fait que l'appliquer a la
 * selection. Le serveur reste juge de ce qui peut etre accorde (aucune permission non detenue par
 * l'acteur), ces regles ne sont qu'une aide a la saisie.
 */

export type ModuleState = 'all' | 'some' | 'none';

const unique = (values: string[]) => Array.from(new Set(values));

/**
 * Coche ou decoche une action. Cocher une action coche aussi la consultation du module, sans quoi
 * elle serait inutilisable ; decocher la consultation decoche les actions qui en dependent.
 */
export function togglePermission(
    selected: string[],
    permission: PermissionOption,
    checked: boolean,
    catalogue: PermissionDomain[],
    held: string[],
): string[] {
    if (checked) {
        const requirement =
            permission.requires !== null && held.includes(permission.requires)
                ? [permission.requires]
                : [];

        return unique([...selected, permission.value, ...requirement]);
    }

    const dependents = catalogue
        .flatMap((module) => module.permissions)
        .filter((option) => option.requires === permission.value)
        .map((option) => option.value);

    return selected.filter(
        (value) => value !== permission.value && !dependents.includes(value),
    );
}

/**
 * Coche ou decoche toutes les actions du module que l'acteur peut accorder.
 */
export function toggleModule(
    selected: string[],
    module: PermissionDomain,
    checked: boolean,
    held: string[],
): string[] {
    const grantable = module.permissions
        .map((option) => option.value)
        .filter((value) => held.includes(value));

    return checked
        ? unique([...selected, ...grantable])
        : selected.filter((value) => !grantable.includes(value));
}

export function moduleState(
    selected: string[],
    module: PermissionDomain,
): ModuleState {
    const count = module.permissions.filter((option) =>
        selected.includes(option.value),
    ).length;

    if (count === 0) {
        return 'none';
    }

    return count === module.permissions.length ? 'all' : 'some';
}
