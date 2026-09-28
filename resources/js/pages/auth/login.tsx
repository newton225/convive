import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TenantInvitationAlert from '@/components/tenant-invitation-alert';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import PasskeyVerify from '@/components/passkey-verify';
import { translate, useTranslation } from '@/hooks/use-translation';
import type { TenantInvitationContext, Translations } from '@/types';

type Props = {
    status?: string;
    canResetPassword: boolean;
    tenantInvitation?: TenantInvitationContext | null;
};

export default function Login({
    status,
    canResetPassword,
    tenantInvitation,
}: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('account.login.head')} />

            {tenantInvitation && (
                <TenantInvitationAlert
                    invitation={tenantInvitation}
                    action="login"
                />
            )}

            <PasskeyVerify />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('account.fields.email')}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder={t(
                                        'account.placeholders.email',
                                    )}
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">
                                        {t('account.fields.password')}
                                    </Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-sm"
                                            tabIndex={5}
                                        >
                                            {t('account.login.forgot')}
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder={t(
                                        'account.placeholders.password',
                                    )}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">
                                    {t('account.fields.remember')}
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                {t('account.login.submit')}
                            </Button>
                        </div>

                        <div className="text-muted-foreground text-center text-sm">
                            {t('account.login.no_account')}{' '}
                            <TextLink
                                href={register({
                                    query: {
                                        invitation: tenantInvitation?.code,
                                    },
                                })}
                                data-test="register-link"
                                tabIndex={5}
                            >
                                {t('account.login.sign_up')}
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-700 dark:text-green-400">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = ({ translations }: { translations: Translations }) => ({
    title: translate(translations, 'account.login.title'),
    description: translate(translations, 'account.login.description'),
});
