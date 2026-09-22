// Components
import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { SubmitButton } from '@/components/submit-button';
import TextLink from '@/components/text-link';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login } from '@/routes';
import { email } from '@/routes/password';
import { translate, useTranslation } from '@/hooks/use-translation';
import type { Translations } from '@/types';

export default function ForgotPassword({ status }: { status?: string }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('account.forgot_password.head')} />

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <div className="space-y-6">
                <Form {...email.form()}>
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('account.fields.email')}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    autoComplete="off"
                                    autoFocus
                                    placeholder={t(
                                        'account.placeholders.email',
                                    )}
                                />

                                <InputError message={errors.email} />
                            </div>

                            <div className="my-6 flex items-center justify-start">
                                <SubmitButton
                                    className="w-full"
                                    processing={processing}
                                    data-test="email-password-reset-link-button"
                                >
                                    {t('account.forgot_password.submit')}
                                </SubmitButton>
                            </div>
                        </>
                    )}
                </Form>

                <div className="text-muted-foreground space-x-1 text-center text-sm">
                    <span>{t('account.forgot_password.return_to')}</span>
                    <TextLink href={login()}>
                        {t('account.forgot_password.login_link')}
                    </TextLink>
                </div>
            </div>
        </>
    );
}

ForgotPassword.layout = ({ translations }: { translations: Translations }) => ({
    title: translate(translations, 'account.forgot_password.title'),
    description: translate(translations, 'account.forgot_password.description'),
});
