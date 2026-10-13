import type { PublicUnitOption } from '@/types';

export type RecapPerson = {
    role: 'guest' | 'companion';
    name: string;
    unit: string;
    // Le nom du tarif choisi, absent quand l'evenement n'en propose pas.
    category: string | null;
    price: number;
};

export type RegistrationRecap = {
    name: string;
    phone: string;
    email: string;
    people: RecapPerson[];
};

type RecapCategory = { id: number; name: string; price: number };

const text = (data: FormData, key: string): string => {
    const value = data.get(key);

    return typeof value === 'string' ? value.trim() : '';
};

/**
 * Releve ce que l'invite a saisi, tel que le formulaire l'enverrait, pour qu'il le relise avant de
 * valider. Les champs vivent dans le formulaire (non controles) : on les lit, on ne les recopie pas
 * dans un second etat qui pourrait diverger de ce que le serveur recevra.
 */
export function buildRegistrationRecap(
    data: FormData,
    units: PublicUnitOption[],
    categories: RecapCategory[],
    pricePerPerson: number,
): RegistrationRecap {
    const unitName = (id: string) =>
        units.find((unit) => String(unit.id) === id)?.name ?? '';

    const person = (
        role: RecapPerson['role'],
        name: string,
        unitId: string,
        categoryId: string,
    ): RecapPerson => {
        const category =
            categories.find((item) => String(item.id) === categoryId) ?? null;

        return {
            role,
            name,
            unit: unitName(unitId),
            category: category?.name ?? null,
            price: category ? category.price : pricePerPerson,
        };
    };

    const people = [
        person(
            'guest',
            text(data, 'name'),
            text(data, 'unit_id'),
            text(data, 'price_category_id'),
        ),
    ];

    for (let index = 0; data.has(`companions[${index}][name]`); index++) {
        people.push(
            person(
                'companion',
                text(data, `companions[${index}][name]`),
                text(data, `companions[${index}][unit_id]`),
                text(data, `companions[${index}][price_category_id]`),
            ),
        );
    }

    return {
        name: text(data, 'name'),
        phone: text(data, 'phone'),
        email: text(data, 'email'),
        people,
    };
}
