import { router } from '@inertiajs/react';
import { MessageCircle } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { recover } from '@/routes/public/registrations';

/**
 * Sous le refus « une reservation est deja en cours pour ce numero » : l'invite qui a perdu le
 * lien de sa reservation se le fait renvoyer par WhatsApp, au numero de cette reservation.
 */
export function ResumeLinkRecovery({
    token,
    phone,
}: {
    token: string;
    phone: string;
}) {
    const { t } = useTranslation();
    const [sending, setSending] = useState(false);

    return (
        <div
            className="bg-muted/50 space-y-2 rounded-md p-3"
            data-test="resume-recovery"
        >
            <p className="text-sm">{t('guest.registration.recovery.hint')}</p>
            <Button
                type="button"
                variant="outline"
                className="min-h-11"
                disabled={sending || phone === ''}
                onClick={() =>
                    router.post(
                        recover.url(token),
                        { phone },
                        {
                            preserveScroll: true,
                            onStart: () => setSending(true),
                            onFinish: () => setSending(false),
                        },
                    )
                }
                data-test="resume-recovery-send"
            >
                <MessageCircle />
                {t('guest.registration.recovery.action')}
            </Button>
        </div>
    );
}
