import { describe, expect, it } from 'vite-plus/test';
import { buildRegistrationRecap } from './registration-recap';

const units = [
    { id: 1, name: 'QODESH' },
    { id: 2, name: 'CHOSEN' },
];
const categories = [
    { id: 10, name: 'Tarif unique', price: 15000 },
    { id: 11, name: 'VIP', price: 20000 },
];

const form = (entries: Record<string, string>) => {
    const data = new FormData();

    Object.entries(entries).forEach(([key, value]) => data.append(key, value));

    return data;
};

describe('buildRegistrationRecap', () => {
    it("releve l'invite et ses accompagnateurs avec leur unite et leur tarif", () => {
        const recap = buildRegistrationRecap(
            form({
                name: ' Aya Kone ',
                phone: '+2250707070707',
                email: '',
                unit_id: '1',
                price_category_id: '11',
                'companions[0][name]': 'Jean',
                'companions[0][unit_id]': '2',
                'companions[0][price_category_id]': '10',
            }),
            units,
            categories,
            0,
        );

        expect(recap.name).toBe('Aya Kone');
        expect(recap.phone).toBe('+2250707070707');
        expect(recap.people).toEqual([
            {
                role: 'guest',
                name: 'Aya Kone',
                unit: 'QODESH',
                category: 'VIP',
                price: 20000,
            },
            {
                role: 'companion',
                name: 'Jean',
                unit: 'CHOSEN',
                category: 'Tarif unique',
                price: 15000,
            },
        ]);
    });

    it("applique le tarif unique de l'evenement quand il n'a pas de categories", () => {
        const recap = buildRegistrationRecap(
            form({ name: 'Aya', phone: '+2250707070707', unit_id: '1' }),
            units,
            [],
            5000,
        );

        expect(recap.people).toEqual([
            {
                role: 'guest',
                name: 'Aya',
                unit: 'QODESH',
                category: null,
                price: 5000,
            },
        ]);
    });
});
