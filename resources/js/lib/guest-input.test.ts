import { describe, expect, it } from 'vite-plus/test';
import {
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

    it('retire les chiffres et les symboles', () => {
        expect(sanitizePersonName('Jean2 <b>Paul</b>@#!')).toBe('Jean bPaulb');
        expect(sanitizePersonName('1234')).toBe('');
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
