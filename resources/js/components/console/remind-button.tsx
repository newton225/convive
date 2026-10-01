import { router } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { useState } from 'react';
import { SubmitButton } from '@/components/submit-button';
import { useTranslation } from '@/hooks/use-translation';
import { remind } from '@/routes/console/recovery';

type Props = {
    slug: string;
};

/**
 * « Relancer » une organisation en impaye (README ecran 29) : un courriel a ceux qui gerent son
 * abonnement. Sans confirmation : la relance se rattrape, et le serveur n'en laisse partir qu'une
 * par jour.
 */
export function RemindButton({ slug }: Props) {
    const { t } = useTranslation();
    const [processing, setProcessing] = useState(false);

    return (
        <SubmitButton
            type="button"
            variant="outline"
            size="sm"
            processing={processing}
            onClick={() =>
                router.post(
                    remind(slug).url,
                    {},
                    {
                        preserveScroll: true,
                        onStart: () => setProcessing(true),
                        onFinish: () => setProcessing(false),
                    },
                )
            }
            data-test="console-recovery-remind"
        >
            <Send />
            {t('console.recovery.remind')}
        </SubmitButton>
    );
}
