import { Form, Head } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import InputError from '@/components/input-error';
import { useState } from 'react';
import PasswordInput from '@/components/password-input';
import { PasswordStrength } from '@/components/password-strength';
import type { PasswordPolicy } from '@/lib/password-strength';
import TenantInvitationAlert from '@/components/tenant-invitation-alert';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { PhoneField } from '@/components/phone/phone-field';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { privacy, terms } from '@/routes/legal';
import { store } from '@/routes/register';
import { translate, useTranslation } from '@/hooks/use-translation';
import type { Country } from 'react-phone-number-input';
import type { TenantInvitationContext, Translations } from '@/types';
import { RequiredFieldsNote } from '@/components/required-fields-note';

type Props = {
    passwordRules: string;
    tenantInvitation?: TenantInvitationContext | null;
    // Le pays propose devant le telephone, deduit de l'adresse IP.
    defaultCountry: Country;
    // Les regles du serveur, cochees pendant la saisie du mot de passe.
    passwordPolicy: PasswordPolicy;
};

export default function Register({
    passwordRules,
    tenantInvitation,
    defaultCountry,
    passwordPolicy,
}: Props) {
    const { t } = useTranslation();
    const [password, setPassword] = useState('');

    return (
        <>
            <Head title={t('account.register.head')} />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        {tenantInvitation && (
                            <TenantInvitationAlert
                                invitation={tenantInvitation}
                                action="register"
                            />
                        )}

                        {errors.registration ? (
                            <Alert
                                variant="destructive"
                                data-test="register-failed"
                                data-error-for="registration"
                            >
                                <AlertCircle />
                                <AlertDescription>
                                    {errors.registration}
                                </AlertDescription>
                            </Alert>
                        ) : null}

                        <RequiredFieldsNote />
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name" required>
                                    {t('account.fields.name')}
                                </Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    name="name"
                                    placeholder={t('account.placeholders.name')}
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            {/* Une personne invitee rejoint une organisation existante : aucune
                                organisation n'est ouverte pour elle a l'inscription. */}
                            {tenantInvitation ? (
                                <input
                                    type="hidden"
                                    name="invitation"
                                    value={tenantInvitation.code}
                                />
                            ) : (
                                <div className="grid gap-2">
                                    <Label htmlFor="organisation_name" required>
                                        {t('account.fields.organisation_name')}
                                    </Label>
                                    <Input
                                        id="organisation_name"
                                        type="text"
                                        required
                                        tabIndex={1}
                                        autoComplete="organization"
                                        name="organisation_name"
                                        placeholder={t(
                                            'account.placeholders.organisation_name',
                                        )}
                                    />
                                    <InputError
                                        message={errors.organisation_name}
                                    />
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="email" required>
                                    {t('account.fields.email')}
                                </Label>
                                {/* L'adresse de l'invitation, la seule acceptee par le serveur. */}
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    tabIndex={2}
                                    autoComplete="email"
                                    name="email"
                                    defaultValue={tenantInvitation?.email}
                                    readOnly={
                                        tenantInvitation !== null &&
                                        tenantInvitation !== undefined
                                    }
                                    placeholder={t(
                                        'account.placeholders.email',
                                    )}
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="phone" required>
                                    {t('account.fields.phone')}
                                </Label>
                                <PhoneField
                                    id="phone"
                                    name="phone"
                                    defaultCountry={defaultCountry}
                                    required
                                />
                                <InputError message={errors.phone} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password" required>
                                    {t('account.fields.password')}
                                </Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    tabIndex={3}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder={t(
                                        'account.placeholders.password',
                                    )}
                                    passwordrules={passwordRules}
                                    onChange={(event) =>
                                        setPassword(event.target.value)
                                    }
                                />
                                <PasswordStrength
                                    password={password}
                                    policy={passwordPolicy}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation" required>
                                    {t('account.fields.password_confirmation')}
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder={t(
                                        'account.placeholders.password_confirmation',
                                    )}
                                    passwordrules={passwordRules}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            {/* L'acceptation des conditions et de la politique de confidentialite :
                                le serveur garde sa date et la version acceptee. */}
                            <div className="grid gap-2">
                                <div className="flex items-start gap-3">
                                    <Checkbox
                                        id="terms"
                                        name="terms"
                                        required
                                        tabIndex={5}
                                        className="mt-0.5"
                                        data-test="register-terms"
                                    />
                                    <Label
                                        htmlFor="terms"
                                        required
                                        className="block text-sm leading-relaxed font-normal"
                                    >
                                        {t('account.register.terms_prefix')}{' '}
                                        <a
                                            href={terms().url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="underline underline-offset-2"
                                        >
                                            {t('account.register.terms_link')}
                                        </a>{' '}
                                        {t('account.register.terms_joiner')}{' '}
                                        <a
                                            href={privacy().url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="underline underline-offset-2"
                                        >
                                            {t('account.register.privacy_link')}
                                        </a>
                                        .
                                    </Label>
                                </div>
                                <InputError message={errors.terms} />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                tabIndex={6}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                {t('account.register.submit')}
                            </Button>
                        </div>

                        <div className="text-muted-foreground text-center text-sm">
                            {t('account.register.have_account')}{' '}
                            <TextLink
                                href={
                                    tenantInvitation
                                        ? login.url({
                                              query: {
                                                  invitation:
                                                      tenantInvitation.code,
                                              },
                                          })
                                        : login()
                                }
                                data-test="tenant-invitation-login-link"
                                tabIndex={6}
                            >
                                {t('account.register.sign_in')}
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = ({ translations }: { translations: Translations }) => ({
    title: translate(translations, 'account.register.title'),
    description: translate(translations, 'account.register.description'),
});
