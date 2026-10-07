import { describe, expect, it } from 'vite-plus/test';
import { pageWindow } from '@/lib/page-window';

describe('pageWindow', () => {
    it('montre toutes les pages quand il y en a peu', () => {
        expect(pageWindow(1, 1)).toEqual([1]);
        expect(pageWindow(2, 5)).toEqual([1, 2, 3, 4, 5]);
    });

    it('montre les extremites et la page courante entouree de ses voisines', () => {
        expect(pageWindow(50, 100)).toEqual([1, 'gap', 49, 50, 51, 'gap', 100]);
    });

    it('au debut, ne laisse pas de trou avant la page courante', () => {
        expect(pageWindow(1, 100)).toEqual([1, 2, 'gap', 100]);
        expect(pageWindow(3, 100)).toEqual([1, 2, 3, 4, 'gap', 100]);
    });

    it('a la fin, ne laisse pas de trou apres la page courante', () => {
        expect(pageWindow(100, 100)).toEqual([1, 'gap', 99, 100]);
        expect(pageWindow(98, 100)).toEqual([1, 'gap', 97, 98, 99, 100]);
    });

    it('affiche une page isolee plutot qu une ellipse qui ne cacherait qu elle', () => {
        expect(pageWindow(4, 100)).toEqual([1, 2, 3, 4, 5, 'gap', 100]);
    });

    it('ramene une page hors limites dans la plage', () => {
        expect(pageWindow(0, 3)).toEqual([1, 2, 3]);
        expect(pageWindow(9, 3)).toEqual([1, 2, 3]);
    });
});
