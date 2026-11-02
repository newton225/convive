import { describe, expect, it } from 'vite-plus/test';
import {
    isFastEnough,
    megabitsPerSecond,
    probeBytes,
} from './connection-speed';

describe('connection-speed', () => {
    it('convertit des octets et des millisecondes en megabits par seconde', () => {
        expect(megabitsPerSecond(1_000_000, 1000)).toBe(8);
    });

    it('ne divise jamais par zero', () => {
        expect(megabitsPerSecond(probeBytes, 0)).toBe(0);
    });

    it('juge rapide une lecture de 256 Ko en une demi-seconde', () => {
        expect(isFastEnough(probeBytes, 500)).toBe(true);
    });

    it('juge trop lente une lecture de 256 Ko en deux secondes', () => {
        expect(isFastEnough(probeBytes, 2000)).toBe(false);
    });
});
