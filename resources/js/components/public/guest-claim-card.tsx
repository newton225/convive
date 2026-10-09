import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { RequiredFieldsNote } from '@/components/required-fields-note';
import { SubmitButton } from '@/components/submit-button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { store } from '@/routes/public/registrations/claim';
import type { GuestClaimForm } from '@/types';

type Props = {
    token: string;
    claim: GuestClaimForm;
};

const MaxLength = 1000;

/**
 * La reclamation d'un invite (decision du 2026-10-09) : il ecrit a l'organisation sans jamais voir
 * ses coordonnees, et c'est l'organisation qui le rappelle au numero de son dossier.
 */
export function GuestClaimCard({ token, claim }: Props) {
    const { t } = useTranslation();

    if (!claim.canSubmit) {
        return (
            <Card data-test="guest-claim-locked">
                <CardHeader>
                    <CardTitle className="text-base">
                        {t('guest.claim.title')}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-muted-foreground text-sm">
                        {t('guest.claim.locked')}
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Form
            {...store.form([token, claim.registrationId])}
            resetOnSuccess
            data-test="guest-claim-form"
        >
            {({ errors, processing }) => (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('guest.claim.title')}
                        </CardTitle>
                        <p className="text-muted-foreground text-sm">
                            {t('guest.claim.description')}
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <RequiredFieldsNote />
                        <input
                            type="hidden"
                            name="signature"
                            value={claim.signature}
                        />

                        <div className="space-y-2">
                            <Label htmlFor="claim-category" required>
                                {t('guest.claim.category')}
                            </Label>
                            <Select name="category">
                                <SelectTrigger
                                    id="claim-category"
                                    className="w-full"
                                    data-test="guest-claim-category"
                                >
                                    <SelectValue
                                        placeholder={t(
                                            'guest.claim.category_placeholder',
                                        )}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {claim.categories.map((category) => (
                                        <SelectItem
                                            key={category}
                                            value={category}
                                        >
                                            {t(
                                                `guest.claim.categories.${category}`,
                                            )}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.category} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="claim-message" required>
                                {t('guest.claim.message')}
                            </Label>
                            <Textarea
                                id="claim-message"
                                name="message"
                                rows={4}
                                minLength={10}
                                maxLength={MaxLength}
                                required
                                placeholder={t(
                                    'guest.claim.message_placeholder',
                                )}
                                aria-describedby="claim-message-help"
                                data-test="guest-claim-message"
                            />
                            <p
                                id="claim-message-help"
                                className="text-muted-foreground text-xs"
                            >
                                {t('guest.claim.message_help')}
                            </p>
                            <InputError message={errors.message} />
                            <InputError message={errors.signature} />
                        </div>

                        <SubmitButton
                            className="w-full"
                            processing={processing}
                            data-test="guest-claim-submit"
                        >
                            {t('guest.claim.submit')}
                        </SubmitButton>
                    </CardContent>
                </Card>
            )}
        </Form>
    );
}
