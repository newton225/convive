import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { update } from '@/routes/console/security/single-session';

type Props = {
    singleSession: boolean;
};

/**
 * L'interrupteur « une seule session par compte » : il s'enregistre des qu'on le coche, pour toutes
 * les organisations. Il joue a la connexion suivante, il ne ferme pas les sessions deja ouvertes.
 */
export function SingleSessionToggle({ singleSession }: Props) {
    const { t } = useTranslation();
    const [processing, setProcessing] = useState(false);

    const toggle = (checked: boolean) => {
        router.put(
            update().url,
            { single_session: checked },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <div className="flex items-center gap-2">
            <Checkbox
                id="single-session"
                checked={singleSession}
                disabled={processing}
                onCheckedChange={(checked) => toggle(checked === true)}
                data-test="console-single-session-toggle"
            />
            <Label htmlFor="single-session">
                {t('console.security.single_session.label')}
            </Label>
        </div>
    );
}
