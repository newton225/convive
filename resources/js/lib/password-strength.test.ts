import { describe, expect, it } from 'vite-plus/test';
import type { PasswordPolicy } from '@/lib/password-strength';
import { passwordChecks, passwordScore } from '@/lib/password-strength';

const policy: PasswordPolicy = {
    min: 8,
    mixedCase: true,
    numbers: true,
    symbols: true,
    uncompromised: false,
};

const met = (password: string) =>
    Object.fromEntries(
        passwordChecks(password, policy).map((check) => [check.key, check.met]),
    );

describe('passwordChecks', () => {
    it('coche chaque regle remplie', () => {
        expect(met('Convive-2026!')).toEqual({
            min: true,
            mixedCase: true,
            numbers: true,
            symbols: true,
        });
    });

    it('signale chaque regle manquante, comme le serveur', () => {
        expect(met('Co-26!a').min).toBe(false);
        expect(met('convive-2026!').mixedCase).toBe(false);
        expect(met('CONVIVE-2026!').mixedCase).toBe(false);
        expect(met('Convive-deux!').numbers).toBe(false);
        expect(met('Convive2026').symbols).toBe(false);
    });

    it('compte les lettres accentuees et les symboles comme le serveur', () => {
        expect(met('Éléonore 26').mixedCase).toBe(true);
        expect(met('Éléonore 26').symbols).toBe(true);
    });

    it('ne coche que les regles demandees', () => {
        expect(
            passwordChecks('abc', { ...policy, symbols: false }).map(
                (check) => check.key,
            ),
        ).toEqual(['min', 'mixedCase', 'numbers']);
    });
});

describe('passwordScore', () => {
    it('va de 0 a 1 selon la part des regles remplies', () => {
        expect(passwordScore(passwordChecks('', policy))).toBe(0);
        expect(passwordScore(passwordChecks('Convive-2026!', policy))).toBe(1);
        expect(passwordScore(passwordChecks('convive2026', policy))).toBe(0.5);
    });
});
