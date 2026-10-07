import { usePage } from '@inertiajs/react';
import type { RouteQueryOptions } from '@/wayfinder';

/**
 * Meme valeur que `GettingStarted::ReturnQuery` cote serveur : la carte « Premiers pas » l'ajoute
 * aux liens de ses etapes.
 */
export const gettingStartedReturnQuery = { via: 'getting-started' } as const;

/**
 * Les options de route a passer a l'envoi d'un formulaire d'etape : la page ouverte depuis la carte
 * transmet sa provenance, et le serveur ramene alors au tableau de bord. Ouverte autrement, rien.
 */
export function useGettingStartedReturn(): RouteQueryOptions | undefined {
    const { url } = usePage();
    const via = new URL(url, 'http://localhost').searchParams.get('via');

    return via === gettingStartedReturnQuery.via
        ? { query: gettingStartedReturnQuery }
        : undefined;
}
