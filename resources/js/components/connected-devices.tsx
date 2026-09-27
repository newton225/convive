import { Form } from '@inertiajs/react';
import { Laptop, Smartphone } from 'lucide-react';
import { useRef } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { SubmitButton } from '@/components/submit-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import type { ConnectedDevice } from '@/types';

type Props = {
    devices: ConnectedDevice[];
};

/**
 * Les appareils ou le compte est ouvert (SECURITY.md, « Deconnexion et sessions »), sur le modele de
 * Laravel Jetstream : la liste, puis un seul geste pour fermer toutes les autres sessions, confirme
 * par le mot de passe. Chaque ligne ecrit son etat (« Cet appareil »), jamais par la seule couleur.
 */
export default function ConnectedDevices({ devices }: Props) {
    const { t, locale } = useTranslation();
    const passwordInput = useRef<HTMLInputElement>(null);
    const hasOthers = devices.some((device) => !device.isCurrent);

    return (
        <div className="space-y-6" data-test="connected-devices">
            <Heading
                variant="small"
                title={t('account.devices.title')}
                description={t('account.devices.description')}
            />

            {devices.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    {t('account.devices.empty')}
                </p>
            ) : (
                <ul className="space-y-3">
                    {devices.map((device, index) => {
                        const Icon = device.mobile ? Smartphone : Laptop;

                        return (
                            <li
                                key={`${device.lastActiveAt}-${index}`}
                                className="flex items-start gap-3"
                                data-test="connected-device"
                            >
                                <Icon className="text-muted-foreground mt-0.5 size-5 shrink-0" />
                                <div className="min-w-0 space-y-0.5 text-sm">
                                    <p className="flex flex-wrap items-center gap-2 font-medium">
                                        {device.browser ??
                                            t(
                                                'account.devices.unknown_browser',
                                            )}
                                        {' · '}
                                        {device.platform ??
                                            t(
                                                'account.devices.unknown_platform',
                                            )}
                                        {device.isCurrent ? (
                                            <Badge variant="secondary">
                                                {t('account.devices.current')}
                                            </Badge>
                                        ) : null}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {device.ipAddress ?? ''}
                                        {device.ipAddress ? ' · ' : ''}
                                        {t('account.devices.last_active', {
                                            time: formatDateTime(
                                                device.lastActiveAt,
                                                locale,
                                            ),
                                        })}
                                    </p>
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}

            {hasOthers ? (
                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            variant="outline"
                            data-test="close-other-sessions"
                        >
                            {t('account.devices.trigger')}
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>
                            {t('account.devices.confirm_title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('account.devices.confirm_description')}
                        </DialogDescription>

                        <Form
                            {...SecurityController.destroyOtherSessions.form()}
                            options={{ preserveScroll: true }}
                            onError={() => passwordInput.current?.focus()}
                            resetOnSuccess
                            className="space-y-6"
                        >
                            {({ resetAndClearErrors, processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor="sessions-password"
                                            className="sr-only"
                                        >
                                            {t(
                                                'account.devices.password_label',
                                            )}
                                        </Label>
                                        <PasswordInput
                                            id="sessions-password"
                                            name="password"
                                            ref={passwordInput}
                                            placeholder={t(
                                                'account.placeholders.password',
                                            )}
                                            autoComplete="current-password"
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                variant="secondary"
                                                onClick={() =>
                                                    resetAndClearErrors()
                                                }
                                            >
                                                {t('common.actions.cancel')}
                                            </Button>
                                        </DialogClose>
                                        <SubmitButton
                                            processing={processing}
                                            data-test="confirm-close-other-sessions"
                                        >
                                            {t('account.devices.confirm')}
                                        </SubmitButton>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            ) : null}
        </div>
    );
}
