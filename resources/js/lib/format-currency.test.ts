import { describe, expect, it } from 'vite-plus/test';
import { formatAmount, formatMoney } from '@/lib/format-currency';

// `Intl` sépare les milliers par une espace insécable, fine ou non selon la version : les
// comparaisons se font sur les chiffres et le libellé, pas sur le caractère d'espacement.
const digits = (text: string) => text.replace(/[^\d,.]/g, '');

describe('formatAmount', () => {
    it('écrit un montant en francs CFA, sans décimale, avec le libellé reconnu', () => {
        const text = formatAmount(15000, 'fr');

        expect(digits(text)).toBe('15000');
        expect(text.endsWith('F CFA')).toBe(true);
    });
});

describe('formatMoney', () => {
    it('garde le franc CFA dans son unité, sans le diviser en centimes', () => {
        const text = formatMoney(45000, 'XOF', 'fr');

        expect(digits(text)).toBe('45000');
        expect(text.endsWith('F CFA')).toBe(true);
    });

    it("compte l'euro et le dollar en centimes", () => {
        expect(digits(formatMoney(6900, 'EUR', 'fr'))).toBe('69,00');
        expect(digits(formatMoney(7900, 'USD', 'en'))).toBe('79.00');
    });
});
