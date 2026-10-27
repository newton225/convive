import { render } from '@testing-library/react';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { BotCheck } from '@/components/public/bot-check';

const renderWidget = vi.fn(() => 'widget-1');
const reset = vi.fn();
const remove = vi.fn();
const loadTurnstile = vi.fn();

vi.mock('@/lib/turnstile', () => ({
    loadTurnstile: () => loadTurnstile(),
}));

vi.mock('@/hooks/use-translation', () => ({
    useTranslation: () => ({ t: (key: string) => key, locale: 'fr' }),
}));

const appearance = { resolvedAppearance: 'dark' as 'light' | 'dark' };

vi.mock('@/hooks/use-appearance', () => ({
    useAppearance: () => appearance,
}));

async function settle() {
    await Promise.resolve();
    await Promise.resolve();
}

describe('BotCheck', () => {
    beforeEach(() => {
        appearance.resolvedAppearance = 'dark';
        loadTurnstile.mockResolvedValue({
            render: renderWidget,
            reset,
            remove,
        });
        window.turnstile = { render: renderWidget, reset, remove };
    });

    afterEach(() => {
        vi.clearAllMocks();
        delete window.turnstile;
    });

    it("dessine le widget avec le theme de la page, la largeur du formulaire et l'affichage sur doute", async () => {
        render(<BotCheck siteKey="site-key" resetKey={0} />);
        await settle();

        expect(renderWidget).toHaveBeenCalledWith(expect.any(HTMLElement), {
            sitekey: 'site-key',
            language: 'fr',
            'response-field': true,
            theme: 'dark',
            size: 'flexible',
            appearance: 'interaction-only',
        });
    });

    it('suit le theme choisi par le membre', async () => {
        appearance.resolvedAppearance = 'light';

        render(<BotCheck siteKey="site-key" resetKey={0} />);
        await settle();

        expect(renderWidget).toHaveBeenCalledWith(
            expect.any(HTMLElement),
            expect.objectContaining({ theme: 'light' }),
        );
    });

    it('demande un nouveau jeton apres un envoi refuse, pas au premier affichage', async () => {
        const { rerender } = render(
            <BotCheck siteKey="site-key" resetKey={0} />,
        );
        await settle();

        expect(reset).not.toHaveBeenCalled();

        rerender(<BotCheck siteKey="site-key" resetKey={1} />);

        expect(reset).toHaveBeenCalledWith('widget-1');
    });

    it('retire le widget quand le formulaire disparait', async () => {
        const { unmount } = render(
            <BotCheck siteKey="site-key" resetKey={0} />,
        );
        await settle();

        unmount();

        expect(remove).toHaveBeenCalledWith('widget-1');
    });

    it('laisse la page intacte quand le script est bloque', async () => {
        loadTurnstile.mockRejectedValue(new Error('Turnstile unavailable'));

        const { container } = render(
            <BotCheck siteKey="site-key" resetKey={0} />,
        );
        await settle();

        expect(renderWidget).not.toHaveBeenCalled();
        expect(
            container.querySelector('[data-test="registration-bot-check"]'),
        ).not.toBeNull();
    });

    it("affiche l'erreur du serveur sous le widget", () => {
        const { getByText } = render(
            <BotCheck
                siteKey="site-key"
                resetKey={0}
                error="Verification refusee"
            />,
        );

        expect(getByText('Verification refusee')).toBeTruthy();
    });
});
