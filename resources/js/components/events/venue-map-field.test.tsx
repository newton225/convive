import { fireEvent, render } from '@testing-library/react';
import { describe, expect, it, vi } from 'vite-plus/test';
import { VenueMapField } from '@/components/events/venue-map-field';

vi.mock('@/hooks/use-translation', () => ({
    useTranslation: () => ({ t: (key: string) => key, locale: 'fr' }),
}));

describe('VenueMapField', () => {
    it('propose de tester un lien https', () => {
        const { container } = render(
            <VenueMapField defaultValue="https://www.google.com/maps/place/Abidjan" />,
        );

        expect(
            container
                .querySelector('[data-test="event-venue-map-preview"]')
                ?.getAttribute('href'),
        ).toBe('https://www.google.com/maps/place/Abidjan');
    });

    it("n'offre aucun lien pour un autre schema ou des coordonnees", () => {
        const { container, getByRole } = render(
            <VenueMapField defaultValue="" />,
        );
        const input = getByRole('textbox');
        const preview = () =>
            container.querySelector('[data-test="event-venue-map-preview"]');

        fireEvent.change(input, { target: { value: 'javascript:alert(1)' } });
        expect(preview()).toBeNull();

        fireEvent.change(input, { target: { value: '5.3364, -4.0267' } });
        expect(preview()).toBeNull();
    });
});
