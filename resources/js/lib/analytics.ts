/**
 * Google Analytics sur les pages commerciales (README, « Mesure d'audience »).
 *
 * - Rien ne se charge avant le consentement du visiteur.
 * - Une page vue ne porte que l'origine et le chemin, jamais les parametres de l'URL.
 * - Hors des pages commerciales, l'envoi est coupe par le drapeau officiel de Google
 *   (`window['ga-disable-<id>']`) : l'application ne recharge pas la page en passant a la
 *   connexion ou au back-office, le script reste donc charge, mais il n'envoie plus rien.
 */

declare global {
    interface Window {
        dataLayer?: unknown[];
        gtag?: (...args: unknown[]) => void;
        [flag: `ga-disable-${string}`]: boolean | undefined;
    }
}

export type AnalyticsConsent = 'granted' | 'denied';

const ConsentKey = 'convive.analytics-consent';

// Rouvre le bandeau de consentement (lien du pied de page), pour revenir sur son choix.
export const OpenConsentEvent = 'convive:analytics-consent-open';

let loadedId: string | null = null;

export function readConsent(): AnalyticsConsent | null {
    try {
        const value = window.localStorage.getItem(ConsentKey);

        return value === 'granted' || value === 'denied' ? value : null;
    } catch {
        return null;
    }
}

export function storeConsent(consent: AnalyticsConsent): void {
    try {
        window.localStorage.setItem(ConsentKey, consent);
    } catch {
        // Stockage indisponible (navigation privee stricte) : le choix vaut pour cette visite.
    }
}

/**
 * Charge gtag.js une seule fois, sans page vue automatique ni signaux publicitaires : les pages
 * vues sont envoyees a la main, page commerciale par page commerciale.
 */
export function startAnalytics(measurementId: string): void {
    window[`ga-disable-${measurementId}`] = false;

    if (loadedId === measurementId) {
        return;
    }

    loadedId = measurementId;
    window.dataLayer = window.dataLayer ?? [];
    // gtag.js attend l'objet `arguments` lui-meme, pas un tableau : c'est la forme officielle.
    window.gtag = function gtag() {
        // eslint-disable-next-line prefer-rest-params
        window.dataLayer?.push(arguments);
    };

    window.gtag('consent', 'default', {
        analytics_storage: 'granted',
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied',
    });
    window.gtag('js', new Date());
    window.gtag('config', measurementId, {
        send_page_view: false,
        allow_google_signals: false,
        allow_ad_personalization_signals: false,
    });

    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`;
    document.head.appendChild(script);
}

export function trackPageView(): void {
    window.gtag?.('event', 'page_view', {
        page_location: window.location.origin + window.location.pathname,
        page_title: document.title,
    });
}

export function pauseAnalytics(measurementId: string): void {
    window[`ga-disable-${measurementId}`] = true;
}

/**
 * Retire les cookies `_ga` deposes avant un refus : le visiteur qui revient sur son choix ne
 * doit pas continuer a porter un identifiant.
 */
export function forgetAnalyticsCookies(): void {
    const domain = window.location.hostname.split('.').slice(-2).join('.');

    document.cookie.split(';').forEach((cookie) => {
        const name = cookie.split('=')[0]?.trim();

        if (name?.startsWith('_ga')) {
            document.cookie = `${name}=; Max-Age=0; path=/`;
            document.cookie = `${name}=; Max-Age=0; path=/; domain=.${domain}`;
        }
    });
}
