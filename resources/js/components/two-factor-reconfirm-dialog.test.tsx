import { act, fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { TwoFactorReconfirmDialog } from '@/components/two-factor-reconfirm-dialog';

type Listener = (event: {
    detail: {
        errors?: Record<string, string>;
        visit?: { method: string };
    };
}) => void;

// Les evenements du routeur Inertia ecoutes par la fenetre : `before` (l'envoi qui part) et `error`
// (le refus du serveur). On les garde ici pour les declencher a la main.
const listeners: Record<string, Listener[]> = {};
const toastInfo = vi.fn();

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { errors: {} } }),
    useForm: () => ({
        data: { code: '123456' },
        setData: () => undefined,
        // Le serveur accepte le code : succes, puis fin de la visite.
        post: (
            _url: string,
            options: { onSuccess?: () => void; onFinish?: () => void },
        ) => {
            options.onSuccess?.();
            options.onFinish?.();
        },
        reset: () => undefined,
        clearErrors: () => undefined,
        processing: false,
        errors: {},
    }),
    router: {
        on: (name: string, listener: Listener) => {
            (listeners[name] ??= []).push(listener);

            return () => {
                listeners[name].splice(listeners[name].indexOf(listener), 1);
            };
        },
    },
}));

vi.mock('sonner', () => ({
    toast: { info: (message: string) => toastInfo(message) },
}));

vi.mock('@/hooks/use-translation', () => ({
    useTranslation: () => ({ t: (key: string) => key, locale: 'fr' }),
}));

const emit = (name: string, detail: Parameters<Listener>[0]['detail']) =>
    act(() => {
        (listeners[name] ?? []).forEach((listener) => listener({ detail }));
    });

// Un envoi part du bouton qui a le focus, puis le serveur le refuse faute de code recent.
const submitAndRefuse = (button: HTMLButtonElement) => {
    button.focus();
    emit('before', { visit: { method: 'post' } });
    emit('error', { errors: { two_factor_reconfirm: 'code requis' } });
};

describe('TwoFactorReconfirmDialog', () => {
    beforeEach(() => {
        Object.keys(listeners).forEach((name) => delete listeners[name]);
        toastInfo.mockReset();
        document.body.innerHTML = '';
    });

    it('se rouvre a chaque refus, meme identique, apres avoir ete annulee', () => {
        const action = document.body.appendChild(
            document.createElement('button'),
        );
        render(<TwoFactorReconfirmDialog />);

        submitAndRefuse(action);
        expect(
            screen.queryByTestId('two-factor-reconfirm-dialog'),
        ).not.toBeNull();

        fireEvent.click(
            screen.getByText('account.two_factor_reconfirm.cancel'),
        );
        expect(screen.queryByTestId('two-factor-reconfirm-dialog')).toBeNull();

        // Bogue du 2026-10-07 : le second refus, porteur de la meme erreur, ne rouvrait rien.
        submitAndRefuse(action);
        expect(
            screen.queryByTestId('two-factor-reconfirm-dialog'),
        ).not.toBeNull();
    });

    it('rejoue l envoi refuse des que le code est accepte', () => {
        const action = document.body.appendChild(
            document.createElement('button'),
        );
        const clicked = vi.fn();
        action.addEventListener('click', clicked);
        render(<TwoFactorReconfirmDialog />);

        submitAndRefuse(action);
        fireEvent.submit(
            screen
                .getByTestId('two-factor-reconfirm-dialog')
                .querySelector('form')!,
        );

        expect(clicked).toHaveBeenCalledTimes(1);
        expect(toastInfo).not.toHaveBeenCalled();
    });

    it('dit de recliquer quand le bouton de l action a disparu', () => {
        const action = document.body.appendChild(
            document.createElement('button'),
        );
        render(<TwoFactorReconfirmDialog />);

        submitAndRefuse(action);
        action.remove();
        fireEvent.submit(
            screen
                .getByTestId('two-factor-reconfirm-dialog')
                .querySelector('form')!,
        );

        expect(toastInfo).toHaveBeenCalledWith(
            'account.two_factor_reconfirm.confirmed',
        );
    });
});
