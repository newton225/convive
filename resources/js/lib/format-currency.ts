import type { LocaleCode } from '@/types';

/**
 * Montants en francs CFA (README, section 7). Pas de code ISO affiche : "F CFA" est ce que
 * l'invite reconnait, "XOF" ne l'est pas.
 */
export function formatAmount(amount: number, locale: LocaleCode): string {
    return `${new Intl.NumberFormat(locale).format(amount)} F CFA`;
}

/**
 * Montant d'abonnement dans la devise de facturation (franc CFA, euro ou dollar). Les montants
 * arrivent dans la plus petite unite de la devise : le franc CFA n'a pas de decimale, l'euro et le
 * dollar se comptent en centimes. Le franc CFA garde son libelle « F CFA » (voir `formatAmount`).
 */
export function formatMoney(
    minorUnits: number,
    currency: string,
    locale: LocaleCode,
): string {
    if (currency === 'XOF') {
        return formatAmount(minorUnits, locale);
    }

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
    }).format(minorUnits / 100);
}
