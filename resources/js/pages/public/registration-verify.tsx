import { Head, router, useForm, usePage } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import type { FormEvent } from 'react';
import { BrandColorStyle } from '@/components/brand-color-style';
import InputError from '@/components/input-error';
import LocaleSwitcher from '@/components/locale-switcher';
import { OfflineBanner } from '@/components/offline-banner';
import { WhatsAppVerificationCard } from '@/components/public/whatsapp-verification-card';
import { SubmitButton } from '@/components/submit-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { verify } from '@/routes/public/registrations';
import { resend } from '@/routes/public/registrations/verify';
import type { PublicRegistrationTenant } from '@/types';

type Props = {
    token: string;
    resume: string;
    event: { name: string };
    tenant: PublicRegistrationTenant;
    maskedPhone: string;
    codeMinutes: number;
    // WhatsApp des que le numero de Convive recoit les messages des invites, sinon SMS.
    method: 'whatsapp' | 'sms';
    whatsappLink: string | null;
    whatsappCode: string | null;
    whatsappNumber: string | null;
    whatsappVerified: boolean;
};

const CodeLength = 6;

/**
 * Verification du telephone avant la reservation (SECURITY.md C3), quand l'evenement l'exige : une
 * etape, une decision : envoyer un message WhatsApp, ou saisir le code recu par SMS. Aucune place
 * n'est bloquee avant.
 */
export default function RegistrationVerify({
    token,
    resume,
    event,
    tenant,
    maskedPhone,
    codeMinutes,
    method,
    whatsappLink,
    whatsappCode,
    whatsappNumber,
    whatsappVerified,
}: Props) {
    const { t } = useTranslation();
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const form = useForm({ code: '' });

    const submit = (submitEvent: FormEvent) => {
        submitEvent.preventDefault();
        form.post(verify({ token, resume }).url);
    };

    return (
        <div className="bg-background flex min-h-screen flex-col">
            <BrandColorStyle colors={tenant.colors} />
            <Head title={t('guest.phone_verification.title')} />
            <OfflineBanner />

            <header className="flex items-center justify-between p-4">
                <span className="text-sm font-medium">
                    {tenant.displayName}
                </span>
                <LocaleSwitcher />
            </header>

            <main className="mx-auto w-full max-w-lg flex-1 space-y-6 p-4">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-[color:var(--brand-primary)]">
                        {t('guest.phone_verification.title')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {event.name}
                    </p>
                </div>

                {method === 'whatsapp' ? (
                    <WhatsAppVerificationCard
                        token={token}
                        resume={resume}
                        maskedPhone={maskedPhone}
                        link={whatsappLink}
                        code={whatsappCode}
                        number={whatsappNumber}
                        verified={whatsappVerified}
                        error={errors.whatsapp}
                    />
                ) : (
                    <Card>
                        <CardContent className="pt-6">
                            <form
                                onSubmit={submit}
                                className="space-y-5"
                                data-test="phone-verification-form"
                            >
                                <p className="text-sm">
                                    {t('guest.phone_verification.description', {
                                        phone: maskedPhone,
                                    })}
                                </p>

                                <div className="grid gap-2">
                                    <Label htmlFor="code">
                                        {t(
                                            'guest.phone_verification.code_label',
                                        )}
                                    </Label>
                                    <InputOTP
                                        id="code"
                                        name="code"
                                        maxLength={CodeLength}
                                        value={form.data.code}
                                        onChange={(value) =>
                                            form.setData('code', value)
                                        }
                                        pattern={REGEXP_ONLY_DIGITS}
                                        inputMode="numeric"
                                        autoComplete="one-time-code"
                                        autoFocus
                                    >
                                        <InputOTPGroup>
                                            {Array.from(
                                                { length: CodeLength },
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
                                    <p className="text-muted-foreground text-xs">
                                        {t('guest.phone_verification.expires', {
                                            minutes: codeMinutes,
                                        })}
                                    </p>
                                </div>

                                <SubmitButton
                                    className="w-full"
                                    processing={form.processing}
                                    disabled={
                                        form.data.code.length !== CodeLength
                                    }
                                    data-test="phone-verification-submit"
                                >
                                    {t('guest.phone_verification.submit')}
                                </SubmitButton>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="w-full"
                                    data-test="phone-verification-resend"
                                    onClick={() =>
                                        router.post(
                                            resend({ token, resume }).url,
                                        )
                                    }
                                >
                                    {t('guest.phone_verification.resend')}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </main>
        </div>
    );
}
