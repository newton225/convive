import { router, useForm, usePage } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { SubmitButton } from '@/components/submit-button';
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
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { useTranslation } from '@/hooks/use-translation';
import { store } from '@/routes/two-factor/reconfirm';

/**
 * Le code a deux facteurs redemande sur la page meme (`EnsureRecentTwoFactorConfirmation`) :
 * s'ouvre quand le serveur refuse un envoi faute de code recent, et laisse intacts les formulaires
 * de la page. Le code accepte, l'envoi refuse est rejoue (decision du 2026-10-07) : la page reclique
 * sur le bouton qui l'avait lance, par le meme formulaire. Si ce bouton a disparu, un message dit
 * de recliquer : rien n'est enregistre tant que l'action n'est pas renvoyee.
 */
export function TwoFactorReconfirmDialog() {
    const { t } = useTranslation();
    const { errors } = usePage().props;
    const [open, setOpen] = useState(false);
    const form = useForm({ code: '' });
    // Ce qui a lance le dernier envoi (bouton, ou formulaire valide par Entree), puis celui que le
    // serveur a refuse faute de code, a rejouer.
    const lastTrigger = useRef<HTMLElement | null>(null);
    const refused = useRef<HTMLElement | null>(null);

    // Refus present des l'affichage de la page (retour apres une redirection).
    useEffect(() => {
        if (errors.two_factor_reconfirm) {
            setOpen(true);
        }
    }, [errors]);

    // Chaque refus du serveur rouvre la fenetre, meme s'il porte la meme erreur que le precedent :
    // les erreurs de la page restant identiques, l'effet ci-dessus ne se rejouait pas, et apres une
    // annulation seul le toast s'affichait (bogue du 2026-10-07).
    useEffect(() => {
        const offBefore = router.on('before', (event) => {
            if (event.detail.visit.method === 'get') {
                return;
            }

            const active = document.activeElement;
            lastTrigger.current =
                active instanceof HTMLButtonElement
                    ? active
                    : active instanceof HTMLInputElement
                      ? active.form
                      : null;
        });

        const offError = router.on('error', (event) => {
            if (event.detail.errors.two_factor_reconfirm) {
                refused.current = lastTrigger.current;
                setOpen(true);
            }
        });

        return () => {
            offBefore();
            offError();
        };
    }, []);

    const close = () => {
        setOpen(false);
        form.reset();
        form.clearErrors();
    };

    const replay = () => {
        const trigger = refused.current;
        refused.current = null;

        if (trigger instanceof HTMLButtonElement && trigger.isConnected) {
            trigger.click();
        } else if (trigger instanceof HTMLFormElement && trigger.isConnected) {
            trigger.requestSubmit();
        } else {
            toast.info(t('account.two_factor_reconfirm.confirmed'));
        }
    };

    return (
        <>
            {/* La fenetre est la reponse a ce refus : sans cet emplacement declare, toujours present,
                `useVisitFeedback` le doublait d'une notification (la fenetre s'ouvre apres son
                controle). */}
            <span hidden data-error-for="two_factor_reconfirm" />
            <Dialog open={open} onOpenChange={(next) => !next && close()}>
                <DialogContent data-test="two-factor-reconfirm-dialog">
                    <form
                        className="space-y-6"
                        onSubmit={(event) => {
                            event.preventDefault();
                            let accepted = false;

                            form.post(store.url(), {
                                preserveState: true,
                                preserveScroll: true,
                                onSuccess: () => {
                                    accepted = true;
                                    close();
                                },
                                onError: () => form.reset('code'),
                                // Rejoue une fois cette visite terminee, pour que le nouvel envoi
                                // ne l'interrompe pas.
                                onFinish: () => {
                                    if (accepted) {
                                        replay();
                                    }
                                },
                            });
                        }}
                    >
                        <DialogHeader>
                            <DialogTitle>
                                {t('account.two_factor_reconfirm.title')}
                            </DialogTitle>
                            <DialogDescription>
                                {t('account.two_factor_reconfirm.description')}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="flex flex-col items-center gap-3">
                            <InputOTP
                                maxLength={OTP_MAX_LENGTH}
                                value={form.data.code}
                                onChange={(value) =>
                                    form.setData('code', value)
                                }
                                disabled={form.processing}
                                pattern={REGEXP_ONLY_DIGITS}
                                autoFocus
                            >
                                <InputOTPGroup>
                                    {Array.from(
                                        { length: OTP_MAX_LENGTH },
                                        (_, index) => (
                                            <InputOTPSlot
                                                key={index}
                                                index={index}
                                            />
                                        ),
                                    )}
                                </InputOTPGroup>
                            </InputOTP>
                            <InputError message={form.errors.code} />
                        </div>

                        <DialogFooter>
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    {t('account.two_factor_reconfirm.cancel')}
                                </Button>
                            </DialogClose>
                            <SubmitButton
                                processing={form.processing}
                                disabled={
                                    form.data.code.length < OTP_MAX_LENGTH
                                }
                                data-test="two-factor-reconfirm-submit"
                            >
                                {t('account.two_factor_reconfirm.submit')}
                            </SubmitButton>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
