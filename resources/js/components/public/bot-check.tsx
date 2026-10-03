import { useEffect, useRef } from 'react';
import InputError from '@/components/input-error';
import { useTranslation } from '@/hooks/use-translation';
import { loadTurnstile } from '@/lib/turnstile';

type Props = {
    siteKey: string;
    // Change a chaque envoi refuse : le jeton ne sert qu'une fois, il faut en obtenir un nouveau.
    resetKey: number;
    error?: string;
};

/**
 * Le widget anti-robot du formulaire d'inscription (Cloudflare Turnstile). Il pose lui-meme son
 * jeton dans un champ cache du formulaire ; le serveur le verifie aupres de Cloudflare.
 */
export function BotCheck({ siteKey, resetKey, error }: Props) {
    const { locale } = useTranslation();
    const container = useRef<HTMLDivElement>(null);
    const widgetId = useRef<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        loadTurnstile()
            .then((turnstile) => {
                if (cancelled || !container.current) {
                    return;
                }

                widgetId.current = turnstile.render(container.current, {
                    sitekey: siteKey,
                    language: locale,
                    'response-field': true,
                });
            })
            // Script bloque ou injoignable : le serveur refusera l'envoi avec un message clair.
            .catch(() => undefined);

        return () => {
            cancelled = true;

            if (widgetId.current !== null) {
                window.turnstile?.remove(widgetId.current);
                widgetId.current = null;
            }
        };
    }, [siteKey, locale]);

    useEffect(() => {
        if (resetKey > 0 && widgetId.current !== null) {
            window.turnstile?.reset(widgetId.current);
        }
    }, [resetKey]);

    return (
        <div className="space-y-2" data-test="registration-bot-check">
            <div ref={container} />
            <InputError
                message={error}
                data-error-for="cf-turnstile-response"
            />
        </div>
    );
}
