import { useForm, usePage } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useEffect, useState } from 'react';
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
 * de la page. Le code accepte, on clique de nouveau sur le bouton de l'action.
 */
export function TwoFactorReconfirmDialog() {
    const { t } = useTranslation();
    const { errors } = usePage().props;
    const [open, setOpen] = useState(false);
    const form = useForm({ code: '' });

    useEffect(() => {
        if (errors.two_factor_reconfirm) {
            setOpen(true);
        }
    }, [errors]);

    const close = () => {
        setOpen(false);
        form.reset();
        form.clearErrors();
    };

    return (
        <Dialog open={open} onOpenChange={(next) => !next && close()}>
            <DialogContent data-test="two-factor-reconfirm-dialog">
                <form
                    className="space-y-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(store.url(), {
                            preserveState: true,
                            preserveScroll: true,
                            onSuccess: close,
                            onError: () => form.reset('code'),
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
                            onChange={(value) => form.setData('code', value)}
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
                            disabled={form.data.code.length < OTP_MAX_LENGTH}
                            data-test="two-factor-reconfirm-submit"
                        >
                            {t('account.two_factor_reconfirm.submit')}
                        </SubmitButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
