import type { SVGAttributes } from 'react';

/**
 * Le logo de Convive : une table ronde vue de dessus, ouverte sur la place du convive. Meme dessin
 * que les icones de l'application (`public/icons`, `public/favicon.svg`), en une seule couleur :
 * il prend celle du texte qui l'entoure (`fill-current`).
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <path d="M37.27 25.07A18 18 0 1 1 37.27 14.93L29.94 14.93A11.16 11.16 0 1 0 29.94 25.07Z" />
            <circle cx="32.19" cy="20" r="2.9" />
        </svg>
    );
}
