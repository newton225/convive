export type SeatingRegistrationRow = {
    id: number;
    name: string;
    unit: string;
    partySize: number;
};

export type SeatingTableRow = {
    id: number;
    number: number;
    capacity: number;
    remaining: number;
    reservedUnit: string | null;
    occupants: SeatingRegistrationRow[];
};

export type SeatingUnitOption = {
    id: number;
    name: string;
};

export type SeatingConstraintRow = {
    id: number;
    unitA: string;
    unitB: string;
};
