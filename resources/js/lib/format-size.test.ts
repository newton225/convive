import { describe, expect, it } from 'vite-plus/test';
import { formatMegabytes } from '@/lib/format-size';

describe('formatMegabytes', () => {
    it('convertit des octets en mégaoctets, à une décimale', () => {
        expect(formatMegabytes(2 * 1024 * 1024, 'en')).toBe('2.0');
        expect(formatMegabytes(2_979_230, 'en')).toBe('2.8');
    });

    it('écrit la virgule en français', () => {
        expect(formatMegabytes(2_979_230, 'fr')).toBe('2,8');
    });

    it('écrit zéro pour une taille nulle', () => {
        expect(formatMegabytes(0, 'fr')).toBe('0,0');
    });
});
