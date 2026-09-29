import { normalizeForSearch } from '@/lib/search';
import type { PaymentProofRow } from '@/types';

export const ProofSignalFilters = ['all', 'anomaly', 'clean', 'note'] as const;

export type ProofSignalFilter = (typeof ProofSignalFilters)[number];

/**
 * Une anomalie est un soupcon (reference ou capture deja vues, ecart avec le releve) ; la precision
 * de l'invite n'en est pas une, c'est une information a lire. Les deux filtres restent distincts.
 */
function hasAnomaly(proof: PaymentProofRow): boolean {
    const { guestNote: _note, ...suspicions } = proof.signals;

    return Object.values(suspicions).some(Boolean);
}

export function proofMatchesSignal(
    proof: PaymentProofRow,
    filter: ProofSignalFilter,
): boolean {
    switch (filter) {
        case 'anomaly':
            return hasAnomaly(proof);
        case 'clean':
            return !hasAnomaly(proof);
        case 'note':
            return proof.signals.guestNote;
        default:
            return true;
    }
}

/**
 * Recherche sur tout ce qui identifie une preuve : nom, reference de l'inscription et de la
 * transaction, telephone (avec ou sans espaces), unite, accompagnateurs.
 */
export function proofMatchesSearch(
    proof: PaymentProofRow,
    query: string,
): boolean {
    const needle = normalizeForSearch(query);

    if (needle === '') {
        return true;
    }

    const haystack = normalizeForSearch(
        [
            proof.name,
            proof.registrationReference ?? '',
            proof.phone,
            proof.reference ?? '',
            proof.unit,
            ...proof.companions.flatMap((companion) => [
                companion.name,
                companion.unit,
            ]),
        ].join(' '),
    );

    return (
        haystack.includes(needle) ||
        haystack.replace(/\s+/g, '').includes(needle.replace(/\s+/g, ''))
    );
}
