import { Form, Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ConfirmSummary } from '@/components/confirm-summary';
import DataTable from '@/components/data-table';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { SubmitButton } from '@/components/submit-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import { can, Permission } from '@/lib/permissions';
import { sortingFromParam, sortingToParam } from '@/lib/server-sorting';
import { index as eventsIndex } from '@/routes/tenants/events';
import {
    importMethod,
    index,
    resolve,
} from '@/routes/tenants/events/reconciliation';
import type {
    ReconciliationImportSummary,
    ReconciliationFilters,
    ReconciliationLineRow,
    ReconciliationOutcome,
    ReconciliationRegistrationOption,
    ReconciliationStats,
    RegistrationsMeta,
    TenantPermissions,
    Translations,
} from '@/types';

type Props = {
    tenant: { slug: string };
    event: { id: number; name: string };
    permissions: TenantPermissions;
    imports: ReconciliationImportSummary[];
    currentImportId: number | null;
    stats: ReconciliationStats;
    rows: ReconciliationLineRow[];
    meta: RegistrationsMeta;
    registrationOptions: ReconciliationRegistrationOption[];
    filters: ReconciliationFilters;
};

const AllOutcomes = 'all';

const NoRegistration = 'none';

const StatCards = [
    { key: 'matched', stat: 'matched' },
    { key: 'amount_mismatch', stat: 'amountMismatch' },
    { key: 'approximate_name', stat: 'approximateName' },
    { key: 'no_registration', stat: 'noRegistration' },
] as const;

const outcomeVariant = (
    outcome: ReconciliationOutcome,
): 'default' | 'secondary' | 'destructive' =>
    outcome === 'matched'
        ? 'default'
        : outcome === 'no_registration'
          ? 'destructive'
          : 'secondary';

/**
 * README ecran 19 : le rapprochement du releve, etape 9 de « Ordre de construction ». Les lignes
 * d'un seul import sont affichees a la fois, le dernier par defaut ; la pagination passe par le
 * serveur, comme la base d'inscrits.
 */
export default function EventReconciliation({
    tenant,
    event,
    permissions,
    imports,
    currentImportId,
    stats,
    rows,
    meta,
    registrationOptions,
    filters,
}: Props) {
    const { t, locale } = useTranslation();
    const [resolving, setResolving] = useState<ReconciliationLineRow | null>(
        null,
    );
    const [choice, setChoice] = useState<string>(NoRegistration);

    const canImport = can(permissions, Permission.ReconciliationImport);
    const canResolve = can(permissions, Permission.ReconciliationResolve);

    const [search, setSearch] = useState(filters.search ?? '');

    // Changer d'import repart d'une liste complete : ses filtres et son tri ne valent que pour
    // le releve qu'on regardait.
    const navigate = (params: {
        import?: number;
        page?: number;
        search?: string;
        outcome?: string;
        sort?: string;
    }) => {
        const switching = params.import !== undefined;

        router.get(
            index([tenant.slug, event.id]).url,
            {
                import: params.import ?? currentImportId ?? undefined,
                filter: switching
                    ? undefined
                    : {
                          search:
                              (params.search ?? filters.search ?? '') ||
                              undefined,
                          outcome:
                              (params.outcome ??
                                  filters.outcome ??
                                  AllOutcomes) === AllOutcomes
                                  ? undefined
                                  : (params.outcome ?? filters.outcome),
                      },
                sort: switching
                    ? undefined
                    : (params.sort ?? filters.sort ?? undefined),
                page: params.page,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    useEffect(() => {
        const timeout = setTimeout(() => {
            if (search !== (filters.search ?? '')) {
                navigate({ search });
            }
        }, 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    // L'identifiant d'une colonne triable est le champ autorise par `allowedSorts()`.
    const columns: ColumnDef<ReconciliationLineRow>[] = [
        {
            id: 'line_number',
            accessorKey: 'lineNumber',
            header: t('reconciliation.columns.line'),
        },
        {
            id: 'occurred_on',
            accessorKey: 'occurredOn',
            header: t('reconciliation.columns.date'),
        },
        {
            header: t('reconciliation.columns.reference'),
            cell: ({ row }) => row.original.reference ?? '',
        },
        {
            id: 'issuer',
            accessorKey: 'issuer',
            header: t('reconciliation.columns.issuer'),
        },
        {
            id: 'amount',
            accessorKey: 'amount',
            header: t('reconciliation.columns.amount'),
            cell: ({ row }) => formatAmount(row.original.amount, locale),
        },
        {
            header: t('reconciliation.columns.outcome'),
            cell: ({ row }) => (
                <div className="flex flex-wrap items-center gap-1">
                    <Badge variant={outcomeVariant(row.original.outcome)}>
                        {row.original.outcomeLabel}
                    </Badge>
                    {row.original.resolved ? (
                        <Badge variant="outline">
                            {t('reconciliation.actions.seen')}
                        </Badge>
                    ) : null}
                </div>
            ),
        },
        {
            header: t('reconciliation.columns.registration'),
            cell: ({ row }) => row.original.matchedRegistration?.name ?? '',
        },
        {
            header: t('reconciliation.columns.actions'),
            cell: ({ row }) =>
                canResolve && row.original.outcome !== 'matched' ? (
                    <Button
                        variant="secondary"
                        size="sm"
                        data-test="reconciliation-resolve"
                        onClick={() => {
                            setResolving(row.original);
                            setChoice(
                                row.original.matchedRegistration
                                    ? String(
                                          row.original.matchedRegistration.id,
                                      )
                                    : NoRegistration,
                            );
                        }}
                    >
                        {t('reconciliation.actions.resolve')}
                    </Button>
                ) : null,
        },
    ];

    const chosenRegistration =
        registrationOptions.find((option) => String(option.id) === choice) ??
        null;

    return (
        <>
            <Head title={t('reconciliation.title')} />

            <div className="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title={t('reconciliation.title')}
                    description={event.name}
                />

                {canImport ? (
                    <Form
                        {...importMethod.form([tenant.slug, event.id])}
                        resetOnSuccess
                        className="space-y-2"
                    >
                        {({ errors, processing }) => (
                            <>
                                <Label htmlFor="statement-file" required>
                                    {t('reconciliation.import.file_label')}
                                </Label>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Input
                                        id="statement-file"
                                        type="file"
                                        name="file"
                                        accept=".csv,text/csv,text/plain"
                                        className="max-w-sm"
                                        data-test="reconciliation-file"
                                    />
                                    <SubmitButton
                                        processing={processing}
                                        data-test="reconciliation-import"
                                    >
                                        {t('reconciliation.import.submit')}
                                    </SubmitButton>
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    {t('reconciliation.import.hint')}
                                </p>
                                <InputError message={errors.file} />
                            </>
                        )}
                    </Form>
                ) : null}

                {imports.length > 1 && currentImportId !== null ? (
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-muted-foreground text-sm">
                            {t('reconciliation.import.current')}
                        </span>
                        <Select
                            value={String(currentImportId)}
                            onValueChange={(value) =>
                                navigate({ import: Number(value) })
                            }
                        >
                            <SelectTrigger
                                className="w-72"
                                data-test="reconciliation-import-select"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {imports.map((statement) => (
                                    <SelectItem
                                        key={statement.id}
                                        value={String(statement.id)}
                                    >
                                        {statement.filename} (
                                        {t('reconciliation.import.rows', {
                                            count: statement.rowCount,
                                        })}
                                        )
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                ) : null}

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {StatCards.map(({ key, stat }) => (
                        <Card
                            key={key}
                            data-test={`reconciliation-stat-${key}`}
                        >
                            <CardHeader>
                                <CardTitle className="text-muted-foreground text-sm font-normal">
                                    {t(`reconciliation.stats.${key}`)}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-2xl font-semibold">
                                    {stats[stat]}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {currentImportId !== null ? (
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="relative min-w-56 flex-1 sm:max-w-sm">
                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                            <Input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder={t(
                                    'reconciliation.toolbar.search_placeholder',
                                )}
                                aria-label={t('reconciliation.toolbar.search')}
                                className="pl-8"
                                data-test="reconciliation-search"
                            />
                        </div>
                        <Select
                            value={filters.outcome ?? AllOutcomes}
                            onValueChange={(value) =>
                                navigate({ outcome: value })
                            }
                        >
                            <SelectTrigger
                                className="w-56"
                                aria-label={t(
                                    'reconciliation.toolbar.filter_label',
                                )}
                                data-test="reconciliation-outcome-filter"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={AllOutcomes}>
                                    {t('reconciliation.toolbar.all')}
                                </SelectItem>
                                {StatCards.map(({ key }) => (
                                    <SelectItem key={key} value={key}>
                                        {t(`reconciliation.stats.${key}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p
                            className="text-muted-foreground text-sm tabular-nums"
                            aria-live="polite"
                        >
                            {t('reconciliation.toolbar.count', {
                                count: meta.total,
                            })}
                        </p>
                    </div>
                ) : null}

                <DataTable
                    columns={columns}
                    data={rows}
                    meta={meta}
                    onPageChange={(page) => navigate({ page })}
                    sorting={sortingFromParam(filters.sort ?? 'line_number')}
                    onSortingChange={(sorting) =>
                        navigate({ sort: sortingToParam(sorting) })
                    }
                    rowTestId="reconciliation-row"
                    emptyState={
                        <>
                            <p className="font-medium">
                                {t('reconciliation.empty.title')}
                            </p>
                            <p className="text-muted-foreground text-sm">
                                {t('reconciliation.empty.description')}
                            </p>
                        </>
                    }
                />
            </div>

            <Dialog
                open={resolving !== null}
                onOpenChange={(open) => !open && setResolving(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('reconciliation.modals.resolve.title', {
                                line: resolving?.lineNumber ?? '',
                            })}
                        </DialogTitle>
                        <DialogDescription>
                            {t('reconciliation.modals.resolve.description', {
                                issuer: resolving?.issuer ?? '',
                            })}
                        </DialogDescription>
                    </DialogHeader>

                    {resolving ? (
                        <ConfirmSummary
                            testId="reconciliation-resolve-summary"
                            items={[
                                {
                                    label: t('reconciliation.columns.date'),
                                    value: resolving.occurredOn,
                                },
                                {
                                    label: t('reconciliation.columns.issuer'),
                                    value: resolving.issuer,
                                    emphasis: true,
                                },
                                {
                                    label: t(
                                        'reconciliation.columns.reference',
                                    ),
                                    value: resolving.reference ?? '-',
                                    mono: true,
                                },
                                {
                                    label: t('reconciliation.columns.amount'),
                                    value: formatAmount(
                                        resolving.amount,
                                        locale,
                                    ),
                                    emphasis: true,
                                },
                                {
                                    label: t('reconciliation.columns.outcome'),
                                    value: resolving.outcomeLabel,
                                },
                            ]}
                        />
                    ) : null}

                    <div className="space-y-2">
                        <Label>
                            {t(
                                'reconciliation.modals.resolve.registration_label',
                            )}
                        </Label>
                        <Select value={choice} onValueChange={setChoice}>
                            <SelectTrigger data-test="reconciliation-registration">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NoRegistration}>
                                    {t('reconciliation.modals.resolve.none')}
                                </SelectItem>
                                {registrationOptions.map((option) => (
                                    <SelectItem
                                        key={option.id}
                                        value={String(option.id)}
                                    >
                                        {option.name} (
                                        {formatAmount(option.amountDue, locale)}
                                        )
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {/* L'ecart de montant se voit avant d'enregistrer, pas apres. */}
                        {resolving && chosenRegistration ? (
                            <p
                                className={
                                    chosenRegistration.amountDue ===
                                    resolving.amount
                                        ? 'text-muted-foreground text-sm'
                                        : 'text-destructive text-sm font-medium'
                                }
                                data-test="reconciliation-resolve-amount"
                            >
                                {t(
                                    chosenRegistration.amountDue ===
                                        resolving.amount
                                        ? 'reconciliation.modals.resolve.amount_matches'
                                        : 'reconciliation.modals.resolve.amount_differs',
                                    {
                                        amount: formatAmount(
                                            chosenRegistration.amountDue,
                                            locale,
                                        ),
                                    },
                                )}
                            </p>
                        ) : null}
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">
                                {t('common.actions.cancel')}
                            </Button>
                        </DialogClose>

                        <Button
                            data-test="reconciliation-resolve-confirm"
                            onClick={() => {
                                if (resolving) {
                                    router.post(
                                        resolve([
                                            tenant.slug,
                                            event.id,
                                            resolving.id,
                                        ]).url,
                                        {
                                            registration_id:
                                                choice === NoRegistration
                                                    ? null
                                                    : Number(choice),
                                        },
                                        {
                                            preserveScroll: true,
                                            onSuccess: () => setResolving(null),
                                        },
                                    );
                                }
                            }}
                        >
                            {t('reconciliation.modals.resolve.submit')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

EventReconciliation.layout = (props: {
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
            title: translate(props.translations, 'reconciliation.title'),
            href: index([props.tenant.slug, props.event.id]),
        },
    ],
});
