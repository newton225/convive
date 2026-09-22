import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import type { TranslationReplacements, Translations } from '@/types/i18n';

const resolve = (
    translations: Record<string, unknown>,
    key: string,
): string | null => {
    const value = key
        .split('.')
        .reduce<unknown>(
            (branch, segment) =>
                branch && typeof branch === 'object'
                    ? (branch as Record<string, unknown>)[segment]
                    : undefined,
            translations,
        );

    return typeof value === 'string' ? value : null;
};

const substitute = (
    message: string,
    replacements: TranslationReplacements,
): string =>
    Object.entries(replacements).reduce(
        (text, [placeholder, value]) =>
            text.replaceAll(`:${placeholder}`, String(value)),
        message,
    );

/**
 * Selectionne la forme correspondant au nombre, dans la notation Laravel :
 * `{0} aucun|{1} un|[2,*] :count autres`, ou simplement `singulier|pluriel`.
 */
const choose = (message: string, count: number): string => {
    const segments = message.split('|');

    if (segments.length === 1) {
        return message;
    }

    for (const segment of segments) {
        const exact = /^\{(\d+)\}\s*/.exec(segment);

        if (exact && Number(exact[1]) === count) {
            return segment.slice(exact[0].length);
        }

        const range = /^\[(\d+),(\d+|\*)\]\s*/.exec(segment);

        if (range) {
            const lower = Number(range[1]);
            const upper = range[2] === '*' ? Infinity : Number(range[2]);

            if (count >= lower && count <= upper) {
                return segment.slice(range[0].length);
            }
        }
    }

    // Aucune borne ne correspond : on retombe sur la forme singulier/pluriel.
    const plain = segments.filter((segment) => !/^[{[]/.test(segment));

    if (plain.length > 1) {
        return count === 1 ? plain[0] : plain[1];
    }

    return segments[segments.length - 1];
};

// Une cle absente rend la cle elle-meme : le trou est visible a l'ecran et en test,
// plutot que silencieux.
export function translate(
    translations: Translations,
    key: string,
    replacements: TranslationReplacements = {},
): string {
    const message = resolve(translations, key);

    if (message === null) {
        return key;
    }

    const count = replacements.count;

    return substitute(
        typeof count === 'number' ? choose(message, count) : message,
        replacements,
    );
}

export function useTranslation() {
    const { translations, locale, supportedLocales } = usePage().props;

    const t = useCallback(
        (key: string, replacements: TranslationReplacements = {}) =>
            translate(translations, key, replacements),
        [translations],
    );

    return { t, locale, supportedLocales };
}
