import { router } from '@inertiajs/react';
import { Copy, MessageCircle, Send } from 'lucide-react';
import { toast } from 'sonner';
import { SubmitButton } from '@/components/submit-button';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { shared } from '@/routes/tenants/events/registrations/card';
import type { RegistrationCardPerson } from '@/types';

type Props = {
    tenantSlug: string;
    eventId: number;
    registrationId: number;
    person: RegistrationCardPerson;
    // Envoi par l'application : seulement pour l'invite principal, le seul dont on a le numero.
    onSend?: () => void;
    sending?: boolean;
    alreadySent?: boolean;
};

// Lien WhatsApp « cliquer pour discuter » : sur le numero s'il est connu, sinon sans destinataire,
// l'organisateur le choisit dans son telephone.
function whatsappUrl(phone: string | null, message: string): string {
    const digits = phone?.replace(/\D/g, '') ?? '';

    return `https://wa.me/${digits}?text=${encodeURIComponent(message)}`;
}

/**
 * Une personne du groupe dans la fenetre « Carte » (README 2.7) : l'invite principal et sa carte,
 * ou un accompagnateur et son seul billet. Chaque lien qui sort de l'application est trace,
 * parce qu'il permet d'entrer.
 */
export function InvitationCardPerson({
    tenantSlug,
    eventId,
    registrationId,
    person,
    onSend,
    sending = false,
    alreadySent = false,
}: Props) {
    const { t } = useTranslation();

    const trace = (via: 'copy' | 'whatsapp') =>
        router.post(
            shared([tenantSlug, eventId, registrationId]).url,
            { ticket: person.ticketId, via },
            { preserveScroll: true, preserveState: true },
        );

    const copy = async () => {
        if (person.url === null) {
            return;
        }

        try {
            await navigator.clipboard.writeText(person.url);
            toast.success(t('registrations.card.copied'));
            trace('copy');
        } catch {
            toast.error(t('registrations.card.errors.copy_failed'));
        }
    };

    const openWhatsApp = () => {
        if (person.message === null) {
            return;
        }

        // Ouvert dans le geste de l'utilisateur, avant la trace : sinon le navigateur bloque la
        // fenetre comme un popup.
        window.open(
            whatsappUrl(person.phone, person.message),
            '_blank',
            'noopener,noreferrer',
        );
        trace('whatsapp');
    };

    return (
        <li
            className="space-y-2 py-3"
            data-test={
                person.isHolder
                    ? 'invitation-card-holder'
                    : 'invitation-card-companion'
            }
        >
            <div>
                <p className="font-medium">
                    {person.name}
                    <span className="text-muted-foreground font-normal">
                        {' · '}
                        {t(
                            person.isHolder
                                ? 'registrations.card.holder'
                                : 'registrations.card.companion',
                        )}
                    </span>
                </p>
                <p className="text-muted-foreground text-xs">
                    {t(
                        person.isHolder
                            ? 'registrations.card.holder_hint'
                            : 'registrations.card.companion_hint',
                    )}
                </p>
            </div>

            {person.url === null ? (
                <p className="text-destructive text-sm">
                    {t('registrations.card.no_link')}
                </p>
            ) : (
                <div className="flex flex-wrap gap-2">
                    {onSend ? (
                        <SubmitButton
                            type="button"
                            size="sm"
                            processing={sending}
                            onClick={onSend}
                            data-test="invitation-card-send"
                        >
                            <Send />
                            {t(
                                alreadySent
                                    ? 'registrations.card.resend'
                                    : 'registrations.card.send',
                            )}
                        </SubmitButton>
                    ) : null}
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={copy}
                        data-test="invitation-card-copy"
                    >
                        <Copy />
                        {t('registrations.card.copy')}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={openWhatsApp}
                        data-test="invitation-card-whatsapp"
                    >
                        <MessageCircle />
                        {t('registrations.card.whatsapp')}
                    </Button>
                </div>
            )}
        </li>
    );
}
