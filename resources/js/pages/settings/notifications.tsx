import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SubmitButton } from '@/components/submit-button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { translate, useTranslation } from '@/hooks/use-translation';
import { edit, update } from '@/routes/notification-preferences';
import type {
    NotificationChannelOption,
    NotificationPreferenceRow,
    Translations,
} from '@/types';

type Props = {
    preferences: NotificationPreferenceRow[];
    channels: NotificationChannelOption[];
};

/**
 * README section 5 et ecran 25 : le canal de chaque type d'alerte, dans l'application, par
 * courriel ou les deux. Un reglage de la personne, il la suit dans chacune de ses organisations.
 */
export default function NotificationPreferences({
    preferences,
    channels,
}: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('notifications.preferences.head')} />

            <h1 className="sr-only">{t('notifications.preferences.head')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('notifications.preferences.title')}
                    description={t('notifications.preferences.description')}
                />

                <Form
                    {...update.form()}
                    setDefaultsOnSuccess
                    className="space-y-6"
                >
                    {({ errors, processing, isDirty }) => (
                        <>
                            <div className="grid gap-4">
                                {preferences.map((preference) => (
                                    <div
                                        key={preference.type}
                                        className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_14rem] sm:items-center"
                                    >
                                        <Label
                                            htmlFor={`preference-${preference.type}`}
                                        >
                                            {preference.label}
                                        </Label>
                                        <div>
                                            <Select
                                                name={`preferences[${preference.type}]`}
                                                defaultValue={
                                                    preference.channel
                                                }
                                            >
                                                <SelectTrigger
                                                    id={`preference-${preference.type}`}
                                                    data-test={`preference-${preference.type}`}
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {channels.map((channel) => (
                                                        <SelectItem
                                                            key={channel.value}
                                                            value={
                                                                channel.value
                                                            }
                                                        >
                                                            {channel.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={
                                                    errors[
                                                        `preferences.${preference.type}`
                                                    ]
                                                }
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <InputError message={errors.preferences} />

                            <SubmitButton
                                processing={processing}
                                dirty={isDirty}
                                data-test="notification-preferences-save"
                            >
                                {t('common.actions.save')}
                            </SubmitButton>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

NotificationPreferences.layout = ({
    translations,
}: {
    translations: Translations;
}) => ({
    breadcrumbs: [
        {
            title: translate(translations, 'notifications.preferences.head'),
            href: edit(),
        },
    ],
});
