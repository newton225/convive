import { Form, Head } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { SubmitButton } from '@/components/submit-button';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { store } from '@/routes/two-factor/reconfirm';
import { translate, useTranslation } from '@/hooks/use-translation';
import type { Translations } from '@/types';

/**
 * Rejeu du second facteur juste avant une action sensible (comptes de versement, SECURITY.md
 * C1), pose par EnsureRecentTwoFactorConfirmation. Meme forme que confirm-password.tsx (mot de
 * passe), mais un code TOTP au lieu d'un mot de passe : les deux se completent, l'un ne
 * remplace pas l'autre.
 */
export default function ConfirmTwoFactor() {
    const { t } = useTranslation();
    const [code, setCode] = useState<string>('');

    return (
        <>
            <Head title={t('account.two_factor_reconfirm.head')} />

            <Form {...store.form()} resetOnError>
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="flex flex-col items-center justify-center space-y-3">
                            <InputOTP
                                name="code"
                                maxLength={OTP_MAX_LENGTH}
                                value={code}
                                onChange={(value) => setCode(value)}
                                disabled={processing}
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
                            <InputError message={errors.code} />
                        </div>

                        <SubmitButton
                            className="w-full"
                            processing={processing}
                            disabled={code.length < OTP_MAX_LENGTH}
                        >
                            {t('account.two_factor_reconfirm.submit')}
                        </SubmitButton>
                    </div>
                )}
            </Form>
        </>
    );
}

ConfirmTwoFactor.layout = ({
    translations,
}: {
    translations: Translations;
}) => ({
    title: translate(translations, 'account.two_factor_reconfirm.title'),
    description: translate(
        translations,
        'account.two_factor_reconfirm.description',
    ),
});
