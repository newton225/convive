import { Form, Head } from '@inertiajs/react';
import { useRef, useState } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import ConnectedDevices from '@/components/connected-devices';
import { ScanPinForm } from '@/components/scan/scan-pin-form';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { PasswordStrength } from '@/components/password-strength';
import type { PasswordPolicy } from '@/lib/password-strength';
import { SubmitButton } from '@/components/submit-button';
import { RequiredFieldsNote } from '@/components/required-fields-note';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/security';
import type { Props as ManagePasskeysProps } from '@/components/manage-passkeys';
import ManagePasskeys from '@/components/manage-passkeys';
import type { Props as ManageTwoFactorProps } from '@/components/manage-two-factor';
import ManageTwoFactor from '@/components/manage-two-factor';
import { useTemporaryHighlight } from '@/hooks/use-temporary-highlight';
import { translate, useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { TwoFactorDetourNotice } from '@/components/two-factor-detour-notice';
import type { ConnectedDevice, Translations, TwoFactorDetour } from '@/types';

// oxfmt-ignore
type Props = {
    passwordRules: string;
    passwordPolicy: PasswordPolicy;
    devices: ConnectedDevice[];
    hasScanPin: boolean;
    twoFactorDetour: TwoFactorDetour | null;
} & ManagePasskeysProps &
    ManageTwoFactorProps;

export default function Security(props: Props) {
    const { t } = useTranslation();
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const [newPassword, setNewPassword] = useState('');
    // Envoye ici pour activer la double authentification : sa zone est encadree quelques secondes.
    const twoFactorZone = useTemporaryHighlight<HTMLDivElement>(
        props.twoFactorDetour !== null && !props.twoFactorEnabled,
    );

    return (
        <>
            <Head title={t('account.security.head')} />

            <h1 className="sr-only">{t('account.security.head')}</h1>

            {props.twoFactorDetour ? (
                <div className="mb-8">
                    <TwoFactorDetourNotice
                        detour={props.twoFactorDetour}
                        twoFactorEnabled={props.twoFactorEnabled ?? false}
                        onShowZone={twoFactorZone.show}
                    />
                </div>
            ) : null}

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('account.security.title')}
                    description={t('account.security.description')}
                />

                <Form
                    {...SecurityController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    resetOnError={[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]}
                    resetOnSuccess
                    onError={(errors) => {
                        if (errors.password) {
                            passwordInput.current?.focus();
                        }

                        if (errors.current_password) {
                            currentPasswordInput.current?.focus();
                        }
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing, isDirty }) => (
                        <>
                            <RequiredFieldsNote />
                            <div className="grid gap-2">
                                <Label htmlFor="current_password" required>
                                    {t('account.fields.current_password')}
                                </Label>

                                <PasswordInput
                                    id="current_password"
                                    ref={currentPasswordInput}
                                    name="current_password"
                                    className="mt-1 block w-full"
                                    autoComplete="current-password"
                                    placeholder={t(
                                        'account.placeholders.current_password',
                                    )}
                                />

                                <InputError message={errors.current_password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password" required>
                                    {t('account.fields.new_password')}
                                </Label>

                                <PasswordInput
                                    id="password"
                                    ref={passwordInput}
                                    name="password"
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    placeholder={t(
                                        'account.placeholders.new_password',
                                    )}
                                    passwordrules={props.passwordRules}
                                    onChange={(event) =>
                                        setNewPassword(event.target.value)
                                    }
                                />

                                <PasswordStrength
                                    password={newPassword}
                                    policy={props.passwordPolicy}
                                />

                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation" required>
                                    {t('account.fields.password_confirmation')}
                                </Label>

                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    placeholder={t(
                                        'account.placeholders.password_confirmation',
                                    )}
                                    passwordrules={props.passwordRules}
                                />

                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <SubmitButton
                                    processing={processing}
                                    dirty={isDirty}
                                    data-test="update-password-button"
                                >
                                    {t('common.actions.save')}
                                </SubmitButton>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <div
                ref={twoFactorZone.ref}
                data-test="two-factor-zone"
                className={cn(
                    'rounded-lg transition-shadow duration-300',
                    twoFactorZone.active &&
                        'ring-primary ring-offset-background ring-2 ring-offset-8',
                )}
            >
                <ManageTwoFactor
                    canManageTwoFactor={props.canManageTwoFactor}
                    requiresConfirmation={props.requiresConfirmation}
                    twoFactorEnabled={props.twoFactorEnabled}
                    freshRecoveryCodes={props.freshRecoveryCodes}
                    recoveryCodesRemaining={props.recoveryCodesRemaining}
                />
            </div>

            <ManagePasskeys
                canManagePasskeys={props.canManagePasskeys}
                passkeys={props.passkeys}
            />

            <div className="space-y-6" data-test="scan-pin-settings">
                <Heading
                    variant="small"
                    title={t('account.scan_pin.title')}
                    description={t('account.scan_pin.description')}
                />
                <ScanPinForm
                    submitLabel={t(
                        props.hasScanPin
                            ? 'account.scan_pin.change'
                            : 'account.scan_pin.create',
                    )}
                />
            </div>

            <ConnectedDevices devices={props.devices} />
        </>
    );
}

Security.layout = ({ translations }: { translations: Translations }) => ({
    breadcrumbs: [
        {
            title: translate(translations, 'account.security.head'),
            href: edit(),
        },
    ],
});
