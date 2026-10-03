/**
 * Chargement du widget anti-robot de Cloudflare Turnstile (`App\Support\BotCheck`), une seule fois
 * par page, en rendu explicite : le formulaire est dessine par React, apres le chargement du
 * script. Le domaine est autorise par la CSP seulement quand les cles sont reglees.
 *
 * Documentation : https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/
 */

export type TurnstileOptions = {
    sitekey: string;
    language?: string;
    // Le jeton est pose par le widget dans un champ cache `cf-turnstile-response` du formulaire.
    'response-field'?: boolean;
};

export type Turnstile = {
    render: (container: HTMLElement, options: TurnstileOptions) => string;
    reset: (widgetId: string) => void;
    remove: (widgetId: string) => void;
};

declare global {
    interface Window {
        turnstile?: Turnstile;
    }
}

const ScriptUrl =
    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

let loading: Promise<Turnstile> | null = null;

export function loadTurnstile(): Promise<Turnstile> {
    if (window.turnstile) {
        return Promise.resolve(window.turnstile);
    }

    loading ??= new Promise<Turnstile>((resolve, reject) => {
        const script = document.createElement('script');
        script.src = ScriptUrl;
        script.async = true;
        script.onload = () =>
            window.turnstile
                ? resolve(window.turnstile)
                : reject(new Error('Turnstile unavailable'));
        script.onerror = () => {
            loading = null;
            reject(new Error('Turnstile unavailable'));
        };
        document.head.appendChild(script);
    });

    return loading;
}
