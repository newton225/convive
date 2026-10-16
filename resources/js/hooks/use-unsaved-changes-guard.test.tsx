import { render } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { useUnsavedChangesGuard } from '@/hooks/use-unsaved-changes-guard';

type BeforeListener = (event: {
    detail: { visit: { method: string } };
}) => boolean | void;

const listeners: BeforeListener[] = [];

vi.mock('@inertiajs/react', () => ({
    router: {
        on: (_name: string, listener: BeforeListener) => {
            listeners.push(listener);

            return () => listeners.splice(listeners.indexOf(listener), 1);
        },
    },
}));

vi.mock('@/hooks/use-translation', () => ({
    useTranslation: () => ({ t: (key: string) => key }),
}));

function Page({ dirty }: { dirty: boolean }) {
    useUnsavedChangesGuard(dirty);

    return null;
}

const visit = (method: string) => ({ detail: { visit: { method } } });

describe('useUnsavedChangesGuard', () => {
    afterEach(() => {
        vi.restoreAllMocks();
        listeners.length = 0;
    });

    it("demande confirmation avant d'actualiser ou de fermer quand des changements ne sont pas enregistres", () => {
        render(<Page dirty />);

        const event = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
    });

    it('ne dit rien quand tout est enregistre', () => {
        render(<Page dirty={false} />);

        const event = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(false);
        expect(listeners).toHaveLength(0);
    });

    it('demande confirmation avant de changer de page et respecte un refus', () => {
        vi.spyOn(window, 'confirm').mockReturnValue(false);
        render(<Page dirty />);

        expect(listeners[0](visit('get'))).toBe(false);
    });

    it("laisse partir quand l'utilisateur confirme", () => {
        vi.spyOn(window, 'confirm').mockReturnValue(true);
        render(<Page dirty />);

        expect(listeners[0](visit('get'))).not.toBe(false);
    });

    it("ne gene pas l'enregistrement lui-meme, qui n'est pas une navigation", () => {
        const confirm = vi.spyOn(window, 'confirm');
        render(<Page dirty />);

        expect(listeners[0](visit('patch'))).toBeUndefined();
        expect(confirm).not.toHaveBeenCalled();
    });
});
