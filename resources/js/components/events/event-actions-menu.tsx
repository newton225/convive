import { Link, router } from '@inertiajs/react';
import { Ellipsis } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/use-translation';
import { can, Permission } from '@/lib/permissions';
import { duplicate, edit } from '@/routes/tenants/events';
import { index as proofsIndex } from '@/routes/tenants/events/proofs';
import { index as reconciliationIndex } from '@/routes/tenants/events/reconciliation';
import { index as registrationsIndex } from '@/routes/tenants/events/registrations';
import { show as reportShow } from '@/routes/tenants/events/report';
import { index as scanIndex } from '@/routes/tenants/events/scan';
import { index as seatingIndex } from '@/routes/tenants/events/seating';
import { edit as settingsEdit } from '@/routes/tenants/events/settings';
import { edit as ticketTemplateEdit } from '@/routes/tenants/events/ticket-template';
import type { EventListItem, TenantPermissions } from '@/types';

type Props = {
    tenantSlug: string;
    event: EventListItem;
    permissions: TenantPermissions;
    onClose: () => void;
};

/**
 * Toutes les destinations d'un evenement, rangees par moment d'usage (suivi, jour J, fiche)
 * derriere un seul bouton, plutot qu'une rangee de dix liens sur chaque carte.
 */
export function EventActionsMenu({
    tenantSlug,
    event,
    permissions,
    onClose,
}: Props) {
    const { t } = useTranslation();
    const target: [string, number] = [tenantSlug, event.id];
    const allowed = (permission: Parameters<typeof can>[1]) =>
        can(permissions, permission);

    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(event.publicUrl ?? '');
            toast.success(t('events.card.link_copied'));
        } catch {
            toast.error(t('events.card.link_copy_failed'));
        }
    };

    const follow = [
        allowed(Permission.RegistrationsView) && {
            href: registrationsIndex(target).url,
            label: t('events.actions.registrations'),
            test: 'event-registrations',
        },
        allowed(Permission.ProofsView) && {
            href: proofsIndex(target).url,
            label: t('events.actions.proofs'),
            test: 'event-proofs',
        },
        allowed(Permission.ReconciliationImport) && {
            href: reconciliationIndex(target).url,
            label: t('events.actions.reconciliation'),
            test: 'event-reconciliation',
        },
        allowed(Permission.ReportsView) && {
            href: reportShow(target).url,
            label: t('events.actions.report'),
            test: 'event-report',
        },
    ].filter((item) => item !== false);

    const dayOf = [
        allowed(Permission.SeatingView) && {
            href: seatingIndex(target).url,
            label: t('events.actions.seating'),
            test: 'event-seating',
        },
        allowed(Permission.ScanPerform) && {
            href: scanIndex(target).url,
            label: t('events.actions.scan'),
            test: 'event-scan',
        },
    ].filter((item) => item !== false);

    const sheet = [
        {
            href: edit(target).url,
            label: t('events.actions.edit'),
            test: 'event-edit',
        },
        allowed(Permission.EventsUpdate) && {
            href: settingsEdit(target).url,
            label: t('events.settings.link'),
            test: 'event-settings',
        },
        allowed(Permission.EventsUpdate) && {
            href: ticketTemplateEdit(target).url,
            label: t('events.actions.ticket_template'),
            test: 'event-ticket-template',
        },
    ].filter((item) => item !== false);

    // Non modal : « Cloturer » ouvre une fenetre de confirmation depuis ce menu. En mode modal,
    // Radix laisse `pointer-events: none` sur la page quand un dialogue s'ouvre pendant la
    // fermeture du menu, et les boutons de la confirmation ne repondent plus.
    return (
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={t('events.card.more_actions', {
                        name: event.name,
                    })}
                    data-test="event-actions"
                >
                    <Ellipsis />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                {follow.length > 0 ? (
                    <DropdownMenuGroup>
                        <DropdownMenuLabel className="text-muted-foreground text-xs">
                            {t('events.card.group_follow')}
                        </DropdownMenuLabel>
                        {follow.map((item) => (
                            <DropdownMenuItem key={item.test} asChild>
                                <Link href={item.href} data-test={item.test}>
                                    {item.label}
                                </Link>
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuGroup>
                ) : null}

                {dayOf.length > 0 ? (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuGroup>
                            <DropdownMenuLabel className="text-muted-foreground text-xs">
                                {t('events.card.group_day')}
                            </DropdownMenuLabel>
                            {dayOf.map((item) => (
                                <DropdownMenuItem key={item.test} asChild>
                                    <Link
                                        href={item.href}
                                        data-test={item.test}
                                    >
                                        {item.label}
                                    </Link>
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuGroup>
                    </>
                ) : null}

                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuLabel className="text-muted-foreground text-xs">
                        {t('events.card.group_event')}
                    </DropdownMenuLabel>
                    {sheet.map((item) => (
                        <DropdownMenuItem key={item.test} asChild>
                            <Link href={item.href} data-test={item.test}>
                                {item.label}
                            </Link>
                        </DropdownMenuItem>
                    ))}
                    {event.publicUrl ? (
                        <DropdownMenuItem
                            onSelect={copyLink}
                            data-test="event-copy-link"
                        >
                            {t('events.actions.copy_link')}
                        </DropdownMenuItem>
                    ) : null}
                    {allowed(Permission.EventsDuplicate) ? (
                        <DropdownMenuItem
                            onSelect={() => router.post(duplicate(target).url)}
                            data-test="event-duplicate"
                        >
                            {t('events.actions.duplicate')}
                        </DropdownMenuItem>
                    ) : null}
                </DropdownMenuGroup>

                {allowed(Permission.EventsClose) &&
                event.status !== 'closed' ? (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={onClose}
                            data-test="event-close"
                        >
                            {t('events.actions.close')}
                        </DropdownMenuItem>
                    </>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
