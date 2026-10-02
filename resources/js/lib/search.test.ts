import { describe, expect, it } from 'vite-plus/test';
import { normalizeForSearch } from '@/lib/search';

describe('normalizeForSearch', () => {
    it('retire les accents et la casse', () => {
        expect(normalizeForSearch('Kouamé')).toBe('kouame');
        expect(normalizeForSearch('ÉTAT MAJOR')).toBe('etat major');
    });

    it('retire les espaces de début et de fin', () => {
        expect(normalizeForSearch('  Hôtel Ivoire  ')).toBe('hotel ivoire');
    });

    it('fait correspondre une saisie sans accent à un nom accentué', () => {
        const venue = normalizeForSearch('Salle des fêtes de Cocody');

        expect(venue.includes(normalizeForSearch('fetes'))).toBe(true);
        expect(venue.includes(normalizeForSearch('Yopougon'))).toBe(false);
    });
});
