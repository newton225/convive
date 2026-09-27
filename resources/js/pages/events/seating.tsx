import { Head, router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { SubmitButton } from '@/components/submit-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation, translate } from '@/hooks/use-translation';
import { can, Permission } from '@/lib/permissions';
import { unitColorClass } from '@/lib/unit-colors';
import { cn } from '@/lib/utils';
import { index as eventsIndex } from '@/routes/tenants/events';
import { assign, index } from '@/routes/tenants/events/seating';
import {
    destroy as destroyConstraint,
    store as storeConstraint,
} from '@/routes/tenants/events/seating/constraints';
import type {
    SeatingConstraintRow,
    SeatingRegistrationRow,
    SeatingTableRow,
    SeatingUnitOption,
    TenantPermissions,
    Translations,
} from '@/types';

type Props = {
    tenant: { slug: string };
    event: { id: number; name: string };
    permissions: TenantPermissions;
    tables: SeatingTableRow[];
    unseated: SeatingRegistrationRow[];
    units: SeatingUnitOption[];
    constraints: SeatingConstraintRow[];
};

/**
 * Regroupe les occupants par unite (README ecran 21) : trier par unite plutot que de les
 * laisser dans leur ordre d'attribution rend le regroupement visible d'un coup d'oeil, la
 * couleur (`unitColorClass`) faisant le reste.
 */
function sortedByUnit(
    occupants: SeatingRegistrationRow[],
): SeatingRegistrationRow[] {
    return [...occupants].sort((a, b) => a.unit.localeCompare(b.unit));
}

/**
 * README ecran 21 : le plan de salle, etape 6 de « Ordre de construction ». L'attribution
 * automatique se joue a la validation d'une preuve ; cet ecran couvre le placement manuel
 * (README 2.6) que l'organisateur doit toujours pouvoir faire, et que
 * `App\Actions\Seating\MoveRegistrationToTable` journalise.
 */
export default function EventSeating({
    tenant,
    event,
    permissions,
    tables,
    unseated,
    units,
    constraints,
}: Props) {
    const { t } = useTranslation();
    const [pending, setPending] = useState<number | null>(null);
    const canAssign = can(permissions, Permission.SeatingAssign);

    const options = tables.map((table) => ({
        value: String(table.id),
        label: `#${table.number} (${table.remaining}/${table.capacity})`,
        disabled: table.remaining <= 0,
    }));

    function moveTo(registrationId: number, seatingTableId: number | null) {
        setPending(registrationId);
        router.post(
            assign([tenant.slug, event.id, registrationId]).url,
            { seating_table_id: seatingTableId },
            { onFinish: () => setPending(null) },
        );
    }

    return (
        <>
            <Head title={t('seating.title')} />

            <div className="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title={t('seating.title')}
                    description={event.name}
                />

                <div>
                    <h2 className="mb-3 text-sm font-medium">
                        {t('seating.tables.title')}
                    </h2>

                    {tables.length === 0 ? (
                        <div className="rounded-lg border p-6 text-center">
                            <p className="text-muted-foreground text-sm">
                                {t('seating.tables.empty')}
                            </p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {tables.map((table) => (
                                <Card key={table.id} data-test="seating-table">
                                    <CardHeader>
                                        <CardTitle className="flex items-center justify-between text-base">
                                            <span>#{table.number}</span>
                                            <span className="text-muted-foreground text-xs font-normal">
                                                {t('seating.tables.seats', {
                                                    used: String(
                                                        table.capacity -
                                                            table.remaining,
                                                    ),
                                                    capacity: String(
                                                        table.capacity,
                                                    ),
                                                })}
                                            </span>
                                        </CardTitle>
                                        {table.reservedUnit ? (
                                            <Badge
                                                variant="secondary"
                                                className="w-fit gap-1.5"
                                            >
                                                <span
                                                    className={cn(
                                                        'size-2 rounded-full',
                                                        unitColorClass(
                                                            table.reservedUnit,
                                                        ),
                                                    )}
                                                />
                                                {t(
                                                    'seating.tables.reserved_for',
                                                    {
                                                        unit: table.reservedUnit,
                                                    },
                                                )}
                                            </Badge>
                                        ) : null}
                                    </CardHeader>
                                    <CardContent className="space-y-2">
                                        {/* Regroupees par unite (README ecran 21) : la meme
                                        couleur d'un coup d'oeil, plutot qu'un simple libelle
                                        au fil des occupants dans leur ordre d'attribution. */}
                                        {sortedByUnit(table.occupants).map(
                                            (occupant) => (
                                                <div
                                                    key={occupant.id}
                                                    className="flex items-center justify-between gap-2 text-sm"
                                                    data-test="seating-occupant"
                                                >
                                                    <div className="flex items-start gap-2">
                                                        <span
                                                            className={cn(
                                                                'mt-1.5 size-2 shrink-0 rounded-full',
                                                                unitColorClass(
                                                                    occupant.unit,
                                                                ),
                                                            )}
                                                        />
                                                        <div>
                                                            <p className="font-medium">
                                                                {occupant.name}
                                                            </p>
                                                            <p className="text-muted-foreground text-xs">
                                                                {occupant.unit}{' '}
                                                                ·{' '}
                                                                {
                                                                    occupant.partySize
                                                                }
                                                            </p>
                                                        </div>
                                                    </div>
                                                    {canAssign ? (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            disabled={
                                                                pending ===
                                                                occupant.id
                                                            }
                                                            data-test="seating-remove"
                                                            onClick={() =>
                                                                moveTo(
                                                                    occupant.id,
                                                                    null,
                                                                )
                                                            }
                                                        >
                                                            {t(
                                                                'seating.actions.remove',
                                                            )}
                                                        </Button>
                                                    ) : null}
                                                </div>
                                            ),
                                        )}
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>

                <div>
                    <h2 className="mb-3 text-sm font-medium">
                        {t('seating.unseated.title')}
                    </h2>

                    {unseated.length === 0 ? (
                        <div className="rounded-lg border p-6 text-center">
                            <p className="text-muted-foreground text-sm">
                                {t('seating.unseated.empty')}
                            </p>
                        </div>
                    ) : (
                        <div className="divide-y rounded-lg border">
                            {unseated.map((registration) => (
                                <div
                                    key={registration.id}
                                    className="flex flex-wrap items-center justify-between gap-3 p-3"
                                    data-test="seating-unseated-row"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {registration.name}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {registration.unit} ·{' '}
                                            {registration.partySize}
                                        </p>
                                    </div>

                                    {canAssign ? (
                                        <Select
                                            disabled={
                                                pending === registration.id
                                            }
                                            onValueChange={(value) =>
                                                moveTo(
                                                    registration.id,
                                                    Number(value),
                                                )
                                            }
                                        >
                                            <SelectTrigger
                                                size="sm"
                                                data-test="seating-assign-select"
                                            >
                                                <SelectValue
                                                    placeholder={t(
                                                        'seating.actions.choose_table',
                                                    )}
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {options.map((option) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        value={option.value}
                                                        disabled={
                                                            option.disabled
                                                        }
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    ) : null}
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <SeatingConstraints
                    tenant={tenant}
                    event={event}
                    units={units}
                    constraints={constraints}
                    canManage={canAssign}
                />
            </div>
        </>
    );
}

function SeatingConstraints({
    tenant,
    event,
    units,
    constraints,
    canManage,
}: {
    tenant: { slug: string };
    event: { id: number };
    units: SeatingUnitOption[];
    constraints: SeatingConstraintRow[];
    canManage: boolean;
}) {
    const { t } = useTranslation();
    const [unitId, setUnitId] = useState<string>('');
    const [otherUnitId, setOtherUnitId] = useState<string>('');
    const [processing, setProcessing] = useState(false);
    const [removing, setRemoving] = useState<number | null>(null);

    function addConstraint() {
        if (!unitId || !otherUnitId) {
            return;
        }

        setProcessing(true);
        router.post(
            storeConstraint([tenant.slug, event.id]).url,
            { unit_id: unitId, other_unit_id: otherUnitId },
            {
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setUnitId('');
                    setOtherUnitId('');
                },
            },
        );
    }

    function removeConstraint(constraintId: number) {
        setRemoving(constraintId);
        router.delete(
            destroyConstraint([tenant.slug, event.id, constraintId]).url,
            { onFinish: () => setRemoving(null) },
        );
    }

    return (
        <div>
            <h2 className="mb-1 text-sm font-medium">
                {t('seating.constraints.title')}
            </h2>
            <p className="text-muted-foreground mb-3 text-sm">
                {t('seating.constraints.description')}
            </p>

            {constraints.length === 0 ? (
                <div className="rounded-lg border p-6 text-center">
                    <p className="text-muted-foreground text-sm">
                        {t('seating.constraints.empty')}
                    </p>
                </div>
            ) : (
                <ul className="mb-4 divide-y rounded-lg border">
                    {constraints.map((constraint) => (
                        <li
                            key={constraint.id}
                            className="flex items-center justify-between gap-3 p-3 text-sm"
                            data-test="seating-constraint-row"
                        >
                            <span>
                                {t('seating.constraints.pair', {
                                    unitA: constraint.unitA,
                                    unitB: constraint.unitB,
                                })}
                            </span>
                            {canManage ? (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    disabled={removing === constraint.id}
                                    data-test="seating-constraint-remove"
                                    onClick={() =>
                                        removeConstraint(constraint.id)
                                    }
                                >
                                    <X className="h-4 w-4" />
                                    {t('seating.constraints.remove')}
                                </Button>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}

            {canManage ? (
                <div className="flex flex-wrap items-end gap-2">
                    <Select value={unitId} onValueChange={setUnitId}>
                        <SelectTrigger
                            size="sm"
                            data-test="seating-constraint-unit-a"
                        >
                            <SelectValue
                                placeholder={t('seating.constraints.unit_a')}
                            />
                        </SelectTrigger>
                        <SelectContent>
                            {units.map((unit) => (
                                <SelectItem
                                    key={unit.id}
                                    value={String(unit.id)}
                                >
                                    {unit.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select value={otherUnitId} onValueChange={setOtherUnitId}>
                        <SelectTrigger
                            size="sm"
                            data-test="seating-constraint-unit-b"
                        >
                            <SelectValue
                                placeholder={t('seating.constraints.unit_b')}
                            />
                        </SelectTrigger>
                        <SelectContent>
                            {units.map((unit) => (
                                <SelectItem
                                    key={unit.id}
                                    value={String(unit.id)}
                                >
                                    {unit.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <SubmitButton
                        type="button"
                        size="sm"
                        processing={processing}
                        disabled={
                            !unitId || !otherUnitId || unitId === otherUnitId
                        }
                        data-test="seating-constraint-add"
                        onClick={addConstraint}
                    >
                        {t('seating.constraints.add')}
                    </SubmitButton>
                </div>
            ) : null}
        </div>
    );
}

EventSeating.layout = (props: {
    tenant: { slug: string };
    event: { id: number };
    translations: Translations;
}) => ({
    breadcrumbs: [
        {
            title: translate(props.translations, 'events.title'),
            href: eventsIndex(props.tenant.slug),
        },
        {
            title: translate(props.translations, 'seating.title'),
            href: index([props.tenant.slug, props.event.id]),
        },
    ],
});
