import { Reveal } from './reveal';

type Props = {
    eyebrow: string;
    title: string;
    subtitle?: string;
    align?: 'start' | 'center';
};

/**
 * L'en-tete commun des sections de la vitrine : un sur-titre court, le titre, et au besoin une
 * phrase d'appui. Une seule facon de titrer, pour que la page se lise d'un seul trait.
 */
export function SectionHeading({
    eyebrow,
    title,
    subtitle,
    align = 'start',
}: Props) {
    return (
        <Reveal
            className={
                align === 'center'
                    ? 'mx-auto max-w-2xl text-center'
                    : 'max-w-2xl'
            }
        >
            <p className="text-primary text-sm font-semibold tracking-wide uppercase">
                {eyebrow}
            </p>
            <h2 className="mt-3 text-3xl font-semibold tracking-tight text-balance sm:text-4xl lg:text-5xl">
                {title}
            </h2>
            {subtitle ? (
                <p className="text-muted-foreground mt-4 text-lg">{subtitle}</p>
            ) : null}
        </Reveal>
    );
}
