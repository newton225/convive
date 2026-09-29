import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { ChartNoAxesColumn } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import type { AnalyticsConsent } from '@/lib/analytics';
import {
    forgetAnalyticsCookies,
    OpenConsentEvent,
    pauseAnalytics,
    readConsent,
    startAnalytics,
    storeConsent,
    trackPageView,
} from '@/lib/analytics';
import { EaseOut } from '@/lib/motion';

/**
 * Mesure d'audience d'une page commerciale (README, « Mesure d'audience ») et son bandeau de
 * consentement. Rendue par l'accueil et la vitrine seulement : en quittant ces pages, le composant
 * se demonte et coupe l'envoi, les pages suivantes (connexion, back-office) ne sont jamais
 * mesurees. Sans identifiant configure, il ne rend rien.
 */
export function SiteAnalytics({
    measurementId,
}: {
    measurementId: string | null;
}) {
    const { t } = useTranslation();
    const reduceMotion = useReducedMotion() === true;
    const [consent, setConsent] = useState<AnalyticsConsent | null>(() =>
        readConsent(),
    );
    const [open, setOpen] = useState(() => readConsent() === null);

    useEffect(() => {
        if (!measurementId || consent !== 'granted') {
            return;
        }

        startAnalytics(measurementId);
        trackPageView();

        return () => pauseAnalytics(measurementId);
    }, [measurementId, consent]);

    useEffect(() => {
        const reopen = () => setOpen(true);

        window.addEventListener(OpenConsentEvent, reopen);

        return () => window.removeEventListener(OpenConsentEvent, reopen);
    }, []);

    if (!measurementId) {
        return null;
    }

    const choose = (choice: AnalyticsConsent) => {
        storeConsent(choice);
        setConsent(choice);
        setOpen(false);

        if (choice === 'denied') {
            pauseAnalytics(measurementId);
            forgetAnalyticsCookies();
        }
    };

    return (
        <AnimatePresence>
            {open ? (
                <motion.div
                    role="dialog"
                    aria-labelledby="analytics-consent-title"
                    initial={reduceMotion ? false : { opacity: 0, y: 24 }}
                    animate={{ opacity: 1, y: 0 }}
                    exit={reduceMotion ? undefined : { opacity: 0, y: 24 }}
                    transition={{ duration: 0.35, ease: EaseOut }}
                    className="bg-card text-card-foreground fixed inset-x-4 bottom-4 z-50 max-w-md rounded-2xl border p-5 shadow-lg sm:right-auto sm:left-6"
                    data-test="analytics-consent"
                >
                    <div className="flex items-start gap-3">
                        <ChartNoAxesColumn className="text-primary mt-0.5 size-5 shrink-0" />
                        <div className="space-y-1">
                            <p
                                id="analytics-consent-title"
                                className="font-semibold"
                            >
                                {t('site.consent.title')}
                            </p>
                            <p className="text-muted-foreground text-sm leading-relaxed">
                                {t('site.consent.body')}
                            </p>
                        </div>
                    </div>
                    <div className="mt-4 flex justify-end gap-2">
                        <Button
                            variant="ghost"
                            onClick={() => choose('denied')}
                            data-test="analytics-decline"
                        >
                            {t('site.consent.decline')}
                        </Button>
                        <Button
                            onClick={() => choose('granted')}
                            data-test="analytics-accept"
                        >
                            {t('site.consent.accept')}
                        </Button>
                    </div>
                </motion.div>
            ) : null}
        </AnimatePresence>
    );
}
