import { NonceStyle } from '@/components/nonce-style';

type BrandColors = {
    primary: string;
    secondary: string;
};

type Props = {
    colors: BrandColors;
    /**
     * Selecteur cible pour les variables. `:root` (defaut) convient au parcours invite : la
     * page entiere n'affiche que ce contenu, aucun chrome de back-office a proteger. Un apercu
     * imbrique dans une page authentifiee (billet dans le formulaire de gabarit, README ecran
     * 15) passe une classe scopee : le back-office reste neutre (CLAUDE.md, « Design et
     * experience utilisateur »), la marque ne doit pas deborder sur le reste de l'ecran.
     */
    selector?: string;
};

/**
 * Pose les variables CSS de couleur de marque via une balise `<style>` nonce'e, jamais
 * l'attribut HTML `style="..."` : une CSP stricte sans `unsafe-inline` (SECURITY.md H7) ne
 * peut proteger que des elements `<style>`/`<script>`, jamais un attribut `style` inline, ce
 * qui est une limite du standard CSP et non de l'outillage du projet.
 *
 * Les valeurs de `colors` arrivent deja validees cote serveur par une expression reguliere
 * hexadecimale stricte (CLAUDE.md, « Organisation ») : elles entrent donc sans risque dans le
 * contenu texte de la balise.
 */
export function BrandColorStyle({ colors, selector = ':root' }: Props) {
    return (
        <NonceStyle
            selector={selector}
            declarations={{
                '--brand-primary': colors.primary,
                '--brand-secondary': colors.secondary,
            }}
        />
    );
}
