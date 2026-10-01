/**
 * Une taille de fichier en megaoctets, a une decimale, ecrite dans la langue de l'interface
 * (virgule en francais, point en anglais). L'unite vient du texte traduit qui l'entoure.
 */
export function formatMegabytes(bytes: number, locale: string): string {
    return new Intl.NumberFormat(locale, {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    }).format(bytes / (1024 * 1024));
}
