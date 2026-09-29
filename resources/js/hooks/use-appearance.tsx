import { useSyncExternalStore } from 'react';

export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ResolvedAppearance | 'system';

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly updateAppearance: (mode: Appearance) => void;
};

const listeners = new Set<() => void>();
let currentAppearance: Appearance = 'system';

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredAppearance = (): Appearance => {
    if (typeof window === 'undefined') {
        return 'system';
    }

    return (localStorage.getItem('appearance') as Appearance) || 'system';
};

const isDarkMode = (appearance: Appearance): boolean => {
    return appearance === 'dark' || (appearance === 'system' && prefersDark());
};

const applyTheme = (appearance: Appearance): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const isDark = isDarkMode(appearance);

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
};

// Dernier point touche ou clique : le centre de la propagation du theme. Les appelants (menu de la
// vitrine, onglets des reglages) n'ont ainsi rien a transmettre.
let lastPointer: { x: number; y: number } | null = null;

const rememberPointer = (event: PointerEvent): void => {
    lastPointer = { x: event.clientX, y: event.clientY };
};

/**
 * Change de theme en le propageant en cercle depuis le dernier point touche. L'API de transition
 * de vue fige l'ancien rendu, le nouveau se revele par-dessus. Changement sec quand le navigateur
 * ne connait pas l'API, quand le theme affiche ne change pas, ou pour qui demande moins de
 * mouvement.
 *
 * La zone de contenu du back-office porte son propre nom de transition (app.css) : il est
 * neutralise le temps de la bascule, sinon elle changerait de theme a part, sans le cercle.
 */
const applyThemeWithRipple = (appearance: Appearance): void => {
    const changes =
        isDarkMode(appearance) !==
        document.documentElement.classList.contains('dark');
    const reduceMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;

    if (!changes || reduceMotion || !document.startViewTransition) {
        applyTheme(appearance);

        return;
    }

    const x = lastPointer?.x ?? window.innerWidth / 2;
    const y = lastPointer?.y ?? 0;
    const radius = Math.hypot(
        Math.max(x, window.innerWidth - x),
        Math.max(y, window.innerHeight - y),
    );
    const root = document.documentElement;

    root.classList.add('theme-transition');

    const transition = document.startViewTransition(() =>
        applyTheme(appearance),
    );

    transition.ready
        .then(() => {
            root.animate(
                {
                    clipPath: [
                        `circle(0px at ${x}px ${y}px)`,
                        `circle(${radius}px at ${x}px ${y}px)`,
                    ],
                },
                {
                    duration: 600,
                    easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                    pseudoElement: '::view-transition-new(root)',
                },
            );
        })
        .catch(() => undefined);

    void transition.finished.finally(() =>
        root.classList.remove('theme-transition'),
    );
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

const mediaQuery = (): MediaQueryList | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const handleSystemThemeChange = (): void => applyTheme(currentAppearance);

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (!localStorage.getItem('appearance')) {
        localStorage.setItem('appearance', 'system');
        setCookie('appearance', 'system');
    }

    currentAppearance = getStoredAppearance();
    applyTheme(currentAppearance);

    // Set up system theme change listener
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);

    window.addEventListener('pointerdown', rememberPointer, { passive: true });
}

export function useAppearance(): UseAppearanceReturn {
    const appearance: Appearance = useSyncExternalStore(
        subscribe,
        () => currentAppearance,
        () => 'system',
    );

    const resolvedAppearance: ResolvedAppearance = isDarkMode(appearance)
        ? 'dark'
        : 'light';

    const updateAppearance = (mode: Appearance): void => {
        currentAppearance = mode;

        // Store in localStorage for client-side persistence...
        localStorage.setItem('appearance', mode);

        // Store in cookie for SSR...
        setCookie('appearance', mode);

        applyThemeWithRipple(mode);
        notify();
    };

    return { appearance, resolvedAppearance, updateAppearance } as const;
}
