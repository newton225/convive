import { Form, Head, usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SubmitButton } from '@/components/submit-button';
import { Input } from '@/components/ui/input';
import { RequiredFieldsNote } from '@/components/required-fields-note';
import { Label } from '@/components/ui/label';
import { PhoneField } from '@/components/phone/phone-field';
import type { Country } from 'react-phone-number-input';
import { edit } from '@/routes/profile';
import type { Auth, Translations } from '@/types';
import { send } from '@/routes/verification';
import { translate, useTranslation } from '@/hooks/use-translation';

type PageProps = {
    auth: Auth;
};

export default function Profile({
    mustVerifyEmail,
    status,
    defaultCountry,
}: {
    mustVerifyEmail: boolean;
    status?: string;
    // Le pays propose devant le telephone, deduit de l'adresse IP.
    defaultCountry: Country;
}) {
    const { auth } = usePage<PageProps>().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('account.profile.head')} />

            <h1 className="sr-only">{t('account.profile.head')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('account.profile.title')}
                    description={t('account.profile.description')}
                />

                <Form
                    {...ProfileController.update.form()}
                    setDefaultsOnSuccess
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors, isDirty }) => (
                        <>
                            <RequiredFieldsNote />
                            <div className="grid gap-2">
                                <Label htmlFor="name" required>
                                    {t('account.fields.name')}
                                </Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder={t('account.placeholders.name')}
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email" required>
                                    {t('account.fields.email')}
                                </Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.email}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder={t(
                                        'account.placeholders.email',
                                    )}
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="phone">
                                    {t('account.fields.phone')}
                                </Label>

                                <PhoneField
                                    id="phone"
                                    name="phone"
                                    defaultCountry={defaultCountry}
                                    defaultValue={auth.user.phone}
                                />

                                <p className="text-muted-foreground text-sm">
                                    {t('account.profile.phone_help')}
                                </p>

                                <InputError
                                    className="mt-2"
                                    message={errors.phone}
                                />
                            </div>

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <div>
                                        <p className="text-muted-foreground -mt-4 text-sm">
                                            {t(
                                                'account.profile.email_unverified',
                                            )}{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="text-foreground decoration-muted-foreground/40 underline underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current!"
                                            >
                                                {t(
                                                    'account.profile.resend_link',
                                                )}
                                            </Link>
                                        </p>

                                        {status ===
                                            'verification-link-sent' && (
                                            <div className="mt-2 text-sm font-medium text-green-700 dark:text-green-400">
                                                {t('account.verify_email.sent')}
                                            </div>
                                        )}
                                    </div>
                                )}

                            <div className="flex items-center gap-4">
                                <SubmitButton
                                    processing={processing}
                                    dirty={isDirty}
                                    data-test="update-profile-button"
                                >
                                    {t('common.actions.save')}
                                </SubmitButton>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <DeleteUser />
        </>
    );
}

Profile.layout = ({ translations }: { translations: Translations }) => ({
    breadcrumbs: [
        {
            title: translate(translations, 'account.profile.head'),
            href: edit(),
        },
    ],
});
