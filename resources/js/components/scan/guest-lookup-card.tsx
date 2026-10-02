import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import { SubmitButton } from '@/components/submit-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import { find } from '@/routes/tenants/events/scan';
import type { ScanLookup, ScanLookupTicket } from '@/types';

type Props = {
    tenantSlug: string;
    eventId: number;
    // Resultat de la derniere recherche, null tant que l'agent n'a rien cherche.
    lookup: ScanLookup | null;
    online: boolean;
    // Ecran verrouille ou code de scan pas encore choisi : rien ne se valide (SECURITY.md M8).
    blocked: boolean;
    canForce: boolean;
    processing: boolean;
    onActivity: () => void;
    onAdmit: (ticketId: number, force: boolean, done: () => void) => void;
};

/**
 * Entree sans scan (README ecran 26) : quand le QR ne peut pas etre lu, l'agent retrouve l'invite
 * par la reference de son dossier ou par son nom, puis valide son entree. La confirmation rappelle
 * que c'est lui qui repond de l'identite de la personne : aucune signature n'a ete verifiee.
 */
export function GuestLookupCard({
    tenantSlug,
    eventId,
    lookup,
    online,
    blocked,
    canForce,
    processing,
    onActivity,
    onAdmit,
}: Props) {
    const { t, locale } = useTranslation();
    const [search, setSearch] = useState(lookup?.search ?? '');
    const [searching, setSearching] = useState(false);
    const [selected, setSelected] = useState<ScanLookupTicket | null>(null);

    const forcing = selected !== null && selected.arrivedAt !== null;

    function submit(submitEvent: FormEvent<HTMLFormElement>) {
        submitEvent.preventDefault();
        onActivity();

        router.get(
            find([tenantSlug, eventId]).url,
            { search },
            {
                only: ['lookup'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setSearching(true),
                onFinish: () => setSearching(false),
            },
        );
    }

    function confirm() {
        if (selected === null) {
            return;
        }

        onActivity();
        onAdmit(selected.id, forcing, () => {
            setSelected(null);
            setSearch('');
        });
    }

    return (
        <Card data-test="guest-lookup" data-tour="scan-lookup">
            <CardHeader>
                <CardTitle className="text-base">
                    {t('scan.lookup.title')}
                </CardTitle>
                <CardDescription>
                    {t('scan.lookup.description')}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {online ? (
                    <form
                        className="flex flex-wrap items-end gap-2"
                        onSubmit={submit}
                    >
                        <div className="grid min-w-0 flex-1 gap-2">
                            <Label htmlFor="guest-lookup-search">
                                {t('scan.lookup.label')}
                            </Label>
                            <Input
                                id="guest-lookup-search"
                                name="search"
                                type="search"
                                value={search}
                                maxLength={80}
                                autoComplete="off"
                                placeholder={t('scan.lookup.placeholder')}
                                disabled={blocked}
                                onChange={(changeEvent) =>
                                    setSearch(changeEvent.target.value)
                                }
                                data-test="guest-lookup-search"
                            />
                        </div>
                        <SubmitButton
                            processing={searching}
                            disabled={blocked || search.trim() === ''}
                            data-test="guest-lookup-submit"
                        >
                            <Search aria-hidden="true" />
                            {t('scan.lookup.submit')}
                        </SubmitButton>
                    </form>
                ) : (
                    <p
                        className="text-muted-foreground text-sm"
                        data-test="guest-lookup-offline"
                    >
                        {t('scan.lookup.offline')}
                    </p>
                )}

                {online && lookup ? (
                    <div aria-live="polite" className="space-y-3">
                        {lookup.tooShort ? (
                            <p
                                className="text-muted-foreground text-sm"
                                data-test="guest-lookup-too-short"
                            >
                                {t('scan.lookup.too_short', {
                                    count: lookup.minimumLength,
                                })}
                            </p>
                        ) : lookup.tickets.length === 0 ? (
                            <p
                                className="text-muted-foreground text-sm"
                                data-test="guest-lookup-empty"
                            >
                                {t('scan.lookup.empty')}
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {lookup.tickets.map((ticket) => (
                                    <li
                                        key={ticket.id}
                                        className="flex flex-wrap items-center justify-between gap-3 py-3"
                                        data-test="guest-lookup-row"
                                    >
                                        <div className="min-w-0 space-y-1 text-sm">
                                            <p className="font-medium">
                                                {ticket.name}
                                            </p>
                                            <p className="text-muted-foreground">
                                                {[
                                                    ticket.unit,
                                                    ticket.reference
                                                        ? t(
                                                              'scan.lookup.reference',
                                                              {
                                                                  reference:
                                                                      ticket.reference,
                                                              },
                                                          )
                                                        : null,
                                                    ticket.tableNumber !== null
                                                        ? t(
                                                              'scan.result.table',
                                                              {
                                                                  number: String(
                                                                      ticket.tableNumber,
                                                                  ),
                                                              },
                                                          )
                                                        : null,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </p>
                                            {ticket.guestOf ? (
                                                <p className="text-muted-foreground">
                                                    {t('scan.result.guest_of', {
                                                        name: ticket.guestOf,
                                                    })}
                                                </p>
                                            ) : null}
                                            {ticket.arrivedAt ? (
                                                <Badge
                                                    variant="secondary"
                                                    data-test="guest-lookup-arrived"
                                                >
                                                    {ticket.arrivedBy
                                                        ? t(
                                                              'scan.lookup.arrived_by',
                                                              {
                                                                  time: formatDateTime(
                                                                      ticket.arrivedAt,
                                                                      locale,
                                                                  ),
                                                                  name: ticket.arrivedBy,
                                                              },
                                                          )
                                                        : t(
                                                              'scan.lookup.arrived',
                                                              {
                                                                  time: formatDateTime(
                                                                      ticket.arrivedAt,
                                                                      locale,
                                                                  ),
                                                              },
                                                          )}
                                                </Badge>
                                            ) : null}
                                        </div>

                                        {ticket.arrivedAt === null ? (
                                            <Button
                                                size="sm"
                                                disabled={blocked}
                                                onClick={() =>
                                                    setSelected(ticket)
                                                }
                                                data-test="guest-lookup-admit"
                                            >
                                                {t('scan.lookup.admit')}
                                            </Button>
                                        ) : canForce ? (
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                disabled={blocked}
                                                onClick={() =>
                                                    setSelected(ticket)
                                                }
                                                data-test="guest-lookup-force"
                                            >
                                                {t('scan.result.force')}
                                            </Button>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        )}

                        {lookup.truncated ? (
                            <p className="text-muted-foreground text-xs">
                                {t('scan.lookup.truncated', {
                                    count: lookup.limit,
                                })}
                            </p>
                        ) : null}
                    </div>
                ) : null}
            </CardContent>

            <ConfirmActionDialog
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelected(null);
                    }
                }}
                title={t(
                    forcing
                        ? 'scan.lookup.force_title'
                        : 'scan.lookup.confirm_title',
                )}
                description={t(
                    forcing
                        ? 'scan.lookup.force_body'
                        : 'scan.lookup.confirm_body',
                )}
                confirmLabel={t(
                    forcing ? 'scan.result.force' : 'scan.lookup.admit',
                )}
                onConfirm={confirm}
                processing={processing}
                destructive={forcing}
                testId="guest-lookup-confirm"
            >
                {selected ? (
                    <div className="bg-muted rounded-lg p-3 text-sm">
                        <p className="font-medium">{selected.name}</p>
                        <p className="text-muted-foreground">
                            {[
                                selected.unit,
                                selected.guestOf
                                    ? t('scan.result.guest_of', {
                                          name: selected.guestOf,
                                      })
                                    : null,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                    </div>
                ) : null}
            </ConfirmActionDialog>
        </Card>
    );
}
