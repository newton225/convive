import { describe, expect, it } from 'vite-plus/test';
import { validatedFieldName } from '@/lib/validated-field';

const input = (attributes: Record<string, string>) => {
    const element = document.createElement('input');

    Object.entries(attributes).forEach(([key, value]) =>
        element.setAttribute(key, value),
    );

    return element;
};

describe('validatedFieldName', () => {
    it('garde le nom d\u2019un champ simple', () => {
        expect(validatedFieldName(input({ name: 'ends_at' }))).toBe('ends_at');
    });

    it('passe la notation des formulaires a celle des regles', () => {
        expect(
            validatedFieldName(input({ name: 'price_categories[0][quota]' })),
        ).toBe('price_categories.0.quota');
    });

    it('ignore les fichiers, les champs caches et les cases a cocher', () => {
        expect(
            validatedFieldName(input({ name: 'visual', type: 'file' })),
        ).toBeNull();
        expect(
            validatedFieldName(
                input({ name: 'payment_accounts', type: 'hidden' }),
            ),
        ).toBeNull();
        expect(
            validatedFieldName(input({ name: 'rule', type: 'checkbox' })),
        ).toBeNull();
    });

    it('ignore un champ sans nom et ce qui n\u2019est pas un champ', () => {
        expect(validatedFieldName(input({}))).toBeNull();
        expect(validatedFieldName(document.createElement('div'))).toBeNull();
    });
});
