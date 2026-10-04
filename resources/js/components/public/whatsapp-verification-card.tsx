import { router, usePoll } from '@inertiajs/react';
import { CheckCircle2, Loader2, MessageCircle } from 'lucide-react';
import { useEffect } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { resend, whatsapp } from '@/routes/public/registrations/verify';

type Props = {
    token: string;
    resume: string;
    maskedPhone: string;
    link: string | null;
    code: string | null;
    number: string | null;
    verified: boolean;
    error?: string;
};

// Le message de l'invite arrive au serveur en quelques secondes : la page se tient au courant.
const PollMilliseconds = 3000;

/**
 * Verification du telephone par un message WhatsApp de l'invite : il envoie, depuis son WhatsApp,
 * un message deja ecrit au numero de Convive. La page attend son arrivee, puis continue d'elle-meme
 * vers la reservation.
 */
export function WhatsAppVerificationCard({
    token,
    resume,
    maskedPhone,
    link,
    code,
    number,
    verified,
    error,
}: Props) {
    const { t } = useTranslation();
    const { stop } = usePoll(PollMilliseconds, {
        only: ['whatsappVerified', 'whatsappLink', 'whatsappCode'],
    });

    useEffect(() => {
        if (verified) {
            stop();
            router.post(whatsapp({ token, resume }).url);
        }
    }, [verified, stop, token, resume]);

    const renew = () => router.post(resend({ token, resume }).url);

    return (
        <Card data-test="whatsapp-verification">
            <CardContent className="space-y-5 pt-6">
                <p className="text-sm">
                    {t('guest.phone_verification.whatsapp_description', {
                        phone: maskedPhone,
                    })}
                </p>

                <ol className="text-muted-foreground list-decimal space-y-1 pl-5 text-sm">
                    <li>{t('guest.phone_verification.whatsapp_step_open')}</li>
                    <li>{t('guest.phone_verification.whatsapp_step_send')}</li>
                    <li>{t('guest.phone_verification.whatsapp_step_back')}</li>
                </ol>

                {verified ? (
                    <p
                        className="flex items-center gap-2 text-sm font-medium"
                        data-test="whatsapp-verification-received"
                    >
                        <CheckCircle2 className="size-4" aria-hidden />
                        {t('guest.phone_verification.whatsapp_received')}
                    </p>
                ) : link && code ? (
                    <>
                        <Button
                            className="brand-fill min-h-11 w-full hover:opacity-90"
                            asChild
                        >
                            <a
                                href={link}
                                target="_blank"
                                rel="noreferrer"
                                data-test="whatsapp-verification-open"
                            >
                                <MessageCircle aria-hidden />
                                {t('guest.phone_verification.whatsapp_open')}
                            </a>
                        </Button>

                        <p
                            className="text-muted-foreground flex items-center gap-2 text-sm"
                            role="status"
                        >
                            <Loader2
                                className="size-4 animate-spin motion-reduce:animate-none"
                                aria-hidden
                            />
                            {t('guest.phone_verification.whatsapp_waiting')}
                        </p>

                        <p className="text-muted-foreground text-xs">
                            {t('guest.phone_verification.whatsapp_manual', {
                                code,
                                number: number ?? '',
                            })}
                        </p>
                    </>
                ) : null}

                <InputError message={error} data-error-for="whatsapp" />

                {verified ? (
                    <Button
                        className="min-h-11 w-full"
                        data-test="whatsapp-verification-continue"
                        onClick={() =>
                            router.post(whatsapp({ token, resume }).url)
                        }
                    >
                        {t('guest.phone_verification.whatsapp_continue')}
                    </Button>
                ) : (
                    <Button
                        type="button"
                        variant="ghost"
                        className="w-full"
                        data-test="whatsapp-verification-renew"
                        onClick={renew}
                    >
                        {t('guest.phone_verification.whatsapp_renew')}
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}
