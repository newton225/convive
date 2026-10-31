import { describe, expect, it } from 'vite-plus/test';
import { formatFileSize } from './format-file-size';

describe('formatFileSize', () => {
    it('ecrit les octets sans decimale', () => {
        expect(formatFileSize(512, 'fr')).toBe('512 o');
        expect(formatFileSize(0, 'en')).toBe('0 B');
    });

    it('passe aux Ko, Mo et Go avec une decimale au plus', () => {
        expect(formatFileSize(2048, 'fr')).toBe('2 Ko');
        expect(formatFileSize(25 * 1024 * 1024, 'en')).toBe('25 MB');
        expect(formatFileSize(12.34 * 1024 * 1024, 'en')).toBe('12.3 MB');
        expect(formatFileSize(3 * 1024 ** 3, 'fr')).toBe('3 Go');
    });

    it('suit la langue pour le separateur decimal', () => {
        expect(formatFileSize(12.34 * 1024 * 1024, 'fr')).toMatch(
            /^12,3\s+Mo$/,
        );
    });
});
