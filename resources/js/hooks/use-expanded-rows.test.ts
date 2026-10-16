import { act, renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vite-plus/test';
import { useExpandedRows } from '@/hooks/use-expanded-rows';

const KEY = 'test.single-expand';

describe('useExpandedRows', () => {
    beforeEach(() => {
        window.localStorage.clear();
    });

    it('garde toutes les lignes ouvertes par defaut', () => {
        const { result } = renderHook(() => useExpandedRows(KEY));

        act(() => result.current.onExpandedChange({ a: true }));
        act(() => result.current.onExpandedChange({ a: true, b: true }));

        expect(result.current.expanded).toEqual({ a: true, b: true });
    });

    it('referme les autres lignes quand une seule ligne est voulue', () => {
        const { result } = renderHook(() => useExpandedRows(KEY));

        act(() => result.current.toggleSingleExpand(true));
        act(() => result.current.onExpandedChange({ a: true }));
        act(() => result.current.onExpandedChange({ a: true, b: true }));

        expect(result.current.expanded).toEqual({ b: true });
    });

    it('repart d un tableau replie en passant a une seule ligne', () => {
        const { result } = renderHook(() => useExpandedRows(KEY));

        act(() => result.current.onExpandedChange({ a: true, b: true }));
        act(() => result.current.toggleSingleExpand(true));

        expect(result.current.expanded).toEqual({});
    });
});
