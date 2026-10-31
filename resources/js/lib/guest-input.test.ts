import { describe, expect, it } from 'vite-plus/test';
import {
    finishPersonName,
    formatIvorianDigits,
    ivorianNationalDigits,
    sanitizePersonName,
} from './guest-input';

describe('sanitizePersonName', () => {
    it('garde les lettres accentuees, les espaces, les tirets et les apostrophes', () => {
        expect(sanitizePersonName("Kouamé N'Guessan-Jean")).toBe(
            "Kouamé N'Guessan-Jean",
        );
        expect(sanitizePersonName('N’Dri')).toBe('N’Dri');
    });

    it("ne garde qu'un separateur entre deux groupes de lettres", () => {
        expect(sanitizePersonName('Jean----Paul')).toBe('Jean-Paul');
        expect(sanitizePersonName('Jean    Paul')).toBe('Jean Paul');
        expect(sanitizePersonName("Jean -'Paul")).toBe('Jean Paul');
        expect(
            sanitizePersonName('dfdfdf fgfgfèèèèèè---- """""        ddfdfdfd'),
        ).toBe('dfdfdf fgfgfèè-ddfdfdfd');
    });

    it("n'ecrit jamais trois fois la meme lettre de suite", () => {
        expect(sanitizePersonName('Aaaa Kooone')).toBe('Aa Koone');
        expect(sanitizePersonName('fgfgfèèèèèè')).toBe('fgfgfèè');
    });

    it('retire les separateurs en tete', () => {
        expect(sanitizePersonName('  -Aya')).toBe('Aya');
    });

    it('retire les chiffres et les symboles', () => {
        expect(sanitizePersonName('Jean2 <b>Paul</b>@#!')).toBe('Jean bPaulb');
        expect(sanitizePersonName('1234')).toBe('');
    });
});

describe('finishPersonName', () => {
    it('retire le separateur laisse a la fin', () => {
        expect(finishPersonName('Jean-')).toBe('Jean');
        expect(finishPersonName("Jean Paul '  ")).toBe('Jean Paul');
    });

    it('met une majuscule au debut de chaque mot sans toucher au reste', () => {
        expect(finishPersonName("jean-marc n'guessan")).toBe(
            "Jean-Marc N'Guessan",
        );
        expect(finishPersonName('KONE moussa')).toBe('KONE Moussa');
        expect(finishPersonName('émile kouamé')).toBe('Émile Kouamé');
    });
});

describe('ivorianNationalDigits', () => {
    it('ne garde que les chiffres, dix au plus', () => {
        expect(ivorianNationalDigits('07 07 12 34 56')).toBe('0707123456');
        expect(ivorianNationalDigits('07a07-12.34/56789')).toBe('0707123456');
    });

    it('retire l’indicatif d’un numero colle en entier', () => {
        expect(ivorianNationalDigits('+225 07 07 12 34 56')).toBe('0707123456');
        expect(ivorianNationalDigits('00225 0707123456')).toBe('0707123456');
    });

    it('laisse intacte une saisie en cours', () => {
        expect(ivorianNationalDigits('225')).toBe('225');
        expect(ivorianNationalDigits('0')).toBe('0');
    });
});

describe('formatIvorianDigits', () => {
    it('groupe par paires sans espace final', () => {
        expect(formatIvorianDigits('0707123456')).toBe('07 07 12 34 56');
        expect(formatIvorianDigits('070')).toBe('07 0');
        expect(formatIvorianDigits('07')).toBe('07');
    });
});
