import { Info } from 'lucide-react';
import { SubmitButton } from '@/components/submit-button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Le numero a deja une inscription confirmee pour cet evenement : l'invite en est prevenu, et
 * n'en cree une additionnelle qu'en le confirmant. Le champ cache part avec le formulaire.
 */
export function AdditionalRegistrationNotice({
    message,
    processing,
}: {
    message: string;
    processing: boolean;
}) {
    const { t } = useTranslation();

    return (
        <Alert
            data-test="registration-additional"
            data-error-for="additional_registration"
        >
            <Info />
            <AlertDescription className="space-y-3">
                <p>{message}</p>
                <input type="hidden" name="confirm_additional" value="1" />
                <SubmitButton
                    className="min-h-11"
                    processing={processing}
                    data-test="registration-additional-confirm"
                >
                    {t('guest.registration.additional.confirm')}
                </SubmitButton>
            </AlertDescription>
        </Alert>
    );
}
