import type { LocaleCode } from '@/types';

const units: Record<LocaleCode, string[]> = {
    fr: ['o', 'Ko', 'Mo', 'Go'],
    en: ['B', 'KB', 'MB', 'GB'],
};

/**
 * Une taille de fichier lisible dans la langue de l'interface : « 12,3 Mo » en francais, « 12.3 MB » en anglais.
 * Les multiples sont ceux de 1 024, comme la limite de taille appliquee par le serveur.
 */
export function formatFileSize(bytes: number, locale: LocaleCode): string {
    let value = Math.max(0, bytes);
    let unit = 0;

    while (value >= 1024 && unit < units[locale].length - 1) {
        value /= 1024;
        unit++;
    }

    const formatted = new Intl.NumberFormat(locale, {
        maximumFractionDigits: unit === 0 ? 0 : 1,
    }).format(value);

    return `${formatted} ${units[locale][unit]}`;
}
