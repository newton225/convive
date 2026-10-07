import { useForm } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { SubmitButton } from '@/components/submit-button';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { update } from '@/routes/scan-pin';

const PinLength = 4;

type Props = {
    // Texte du bouton : « Choisir mon code » a la premiere fois, « Changer mon code » ensuite.
    submitLabel: string;
};

/**
 * Choix du code de scan a 4 chiffres (SECURITY.md M8), saisi deux fois : une faute de frappe a la
 * creation enfermerait l'agent hors de son ecran de scan.
 */
export function ScanPinForm({ submitLabel }: Props) {
    const { t } = useTranslation();
    const form = useForm({ pin: '', pin_confirmation: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update().url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const pinField = (
        id: 'pin' | 'pin_confirmation',
        label: string,
        value: string,
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={id} required>
                {label}
            </Label>
            <InputOTP
                id={id}
                name={id}
                maxLength={PinLength}
                value={value}
                onChange={(next) => form.setData(id, next)}
                pattern={REGEXP_ONLY_DIGITS}
                inputMode="numeric"
                autoComplete="off"
            >
                <InputOTPGroup>
                    {Array.from({ length: PinLength }, (_, index) => (
                        <InputOTPSlot key={index} index={index} />
                    ))}
                </InputOTPGroup>
            </InputOTP>
        </div>
    );

    return (
        <form onSubmit={submit} className="space-y-4" data-test="scan-pin-form">
            {pinField('pin', t('account.scan_pin.pin'), form.data.pin)}
            {pinField(
                'pin_confirmation',
                t('account.scan_pin.confirmation'),
                form.data.pin_confirmation,
            )}
            <InputError message={form.errors.pin} />
            <SubmitButton
                processing={form.processing}
                disabled={
                    form.data.pin.length !== PinLength ||
                    form.data.pin_confirmation.length !== PinLength
                }
                data-test="scan-pin-submit"
            >
                {submitLabel}
            </SubmitButton>
        </form>
    );
}
