import { usePage } from '@inertiajs/react';
import { documentCspNonce } from '@/lib/csp-nonce';

type Props = {
    selector: string;
    declarations: Record<string, string | number>;
};

/**
 * Balise `<style>` nonce'e generique, pour toute valeur calculee a l'execution (couleur de
 * marque, largeur de jauge, remplissage de barre) qu'une CSP stricte sans `unsafe-inline`
 * interdit de poser via l'attribut HTML `style` (SECURITY.md H7) : seuls les elements
 * `<style>`/`<script>` sont couvrables par un nonce, jamais un attribut inline. `selector` cible
 * en general une classe scopee generee par l'appelant (`useId()`), pour ne toucher que
 * l'element concerne.
 */
export function NonceStyle({ selector, declarations }: Props) {
    const { cspNonce } = usePage().props;
    const nonce = documentCspNonce(cspNonce);

    const body = Object.entries(declarations)
        .map(([property, value]) => `${property}: ${value};`)
        .join(' ');

    return (
        <style
            nonce={nonce}
            suppressHydrationWarning
        >{`${selector} { ${body} }`}</style>
    );
}
