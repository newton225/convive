import { act, fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vite-plus/test';
import { TwoFactorReconfirmDialog } from '@/components/two-factor-reconfirm-dialog';

type ErrorEvent = { detail: { errors: Record<string, string> } };

// Le refus du serveur arrive par l'evenement `error` du routeur Inertia : on garde ici l'ecouteur
// pour simuler deux refus successifs, identiques.
const listeners: Array<(event: ErrorEvent) => void> = [];

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { errors: {} } }),
    useForm: () => ({
        data: { code: '' },
        setData: () => undefined,
        post: () => undefined,
        reset: () => undefined,
        clearErrors: () => undefined,
        processing: false,
        errors: {},
    }),
    router: {
        on: (name: string, listener: (event: ErrorEvent) => void) => {
            if (name === 'error') {
                listeners.push(listener);
            }

            return () => {
                listeners.splice(listeners.indexOf(listener), 1);
            };
        },
    },
}));

vi.mock('@/hooks/use-translation', () => ({
    useTranslation: () => ({ t: (key: string) => key, locale: 'fr' }),
}));

const refuse = () =>
    act(() => {
        listeners.forEach((listener) =>
            listener({
                detail: { errors: { two_factor_reconfirm: 'code requis' } },
            }),
        );
    });

describe('TwoFactorReconfirmDialog', () => {
    it('se rouvre a chaque refus, meme identique, apres avoir ete annulee', () => {
        render(<TwoFactorReconfirmDialog />);

        refuse();
        expect(
            screen.queryByTestId('two-factor-reconfirm-dialog'),
        ).not.toBeNull();

        fireEvent.click(
            screen.getByText('account.two_factor_reconfirm.cancel'),
        );
        expect(screen.queryByTestId('two-factor-reconfirm-dialog')).toBeNull();

        // Bogue du 2026-10-07 : le second refus, porteur de la meme erreur, ne rouvrait rien.
        refuse();
        expect(
            screen.queryByTestId('two-factor-reconfirm-dialog'),
        ).not.toBeNull();
    });
});
