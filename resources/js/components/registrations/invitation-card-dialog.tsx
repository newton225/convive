import { router } from '@inertiajs/react';
import { useState } from 'react';
import { formatPhoneNumberIntl } from 'react-phone-number-input';
import { ConfirmSummary } from '@/components/confirm-summary';
import InputError from '@/components/input-error';
import { InvitationCardPerson } from '@/components/registrations/invitation-card-person';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import { send } from '@/routes/tenants/events/registrations/card';
import type { RegistrationCard, RegistrationRow } from '@/types';

type Props = {
    tenantSlug: string;
    eventId: number;
    registration: RegistrationRow & { card: RegistrationCard };
    onClose: () => void;
};

/**
 * La carte d'invitation a la main (README 2.7), quand l'envoi automatique n'a pas fonctionne ou
 * qu'un invite a perdu sa carte : l'envoyer depuis l'application, ou transmettre soi-meme le lien
 * de chaque personne du groupe.
 */
export function InvitationCardDialog({
    tenantSlug,
    eventId,
    registration,
    onClose,
}: Props) {
    const { t, locale } = useTranslation();
    const [sending, setSending] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const sendFromApplication = () =>
        router.post(
            send([tenantSlug, eventId, registration.id]).url,
            {},
            {
                preserveScroll: true,
                onStart: () => {
                    setSending(true);
                    setError(null);
                },
                onFinish: () => setSending(false),
                onSuccess: onClose,
                onError: (errors) => setError(errors.card ?? null),
            },
        );

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{t('registrations.card.title')}</DialogTitle>
                    <DialogDescription>
                        {t('registrations.card.description', {
                            name: registration.name,
                        })}
                    </DialogDescription>
                </DialogHeader>

                <ConfirmSummary
                    testId="invitation-card-summary"
                    items={[
                        {
                            label: t('registrations.columns.name'),
                            value: registration.name,
                            emphasis: true,
                        },
                        {
                            label: t('registrations.confirm.reference'),
                            value: registration.reference,
                            mono: true,
                            hidden: registration.reference === null,
                        },
                        {
                            label: t('registrations.confirm.phone'),
                            value:
                                formatPhoneNumberIntl(registration.phone) ||
                                registration.phone,
                        },
                        {
                            label: t('registrations.card.last_sent'),
                            value: registration.cardSentAt
                                ? formatDateTime(
                                      registration.cardSentAt,
                                      locale,
                                  )
                                : t('registrations.card.not_sent'),
                            warning: registration.cardSentAt === null,
                        },
                    ]}
                />

                <ul className="divide-y">
                    {registration.card.people.map((person) => (
                        <InvitationCardPerson
                            key={person.ticketId ?? 'holder'}
                            tenantSlug={tenantSlug}
                            eventId={eventId}
                            registrationId={registration.id}
                            person={person}
                            onSend={
                                person.isHolder
                                    ? sendFromApplication
                                    : undefined
                            }
                            sending={sending}
                            alreadySent={registration.cardSentAt !== null}
                        />
                    ))}
                </ul>

                <InputError
                    message={error ?? undefined}
                    data-error-for="card"
                />

                <p className="text-muted-foreground text-xs">
                    {t('registrations.card.send_hint')}{' '}
                    {t('registrations.card.traced')}
                </p>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            {t('common.actions.close')}
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
