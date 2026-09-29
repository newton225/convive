import { useReducedMotion } from 'framer-motion';
import { useState } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    src: string;
    alt: string;
    // Classes de l'image elle-meme (cadrage, `object-cover`, effet au survol...).
    className?: string;
    // Ce qui reste visible si l'image ne se charge pas : le fond de repli du cadre.
    fallback?: React.ReactNode;
    // Fond du cadre pendant le chargement (gris neutre par defaut, couleurs de marque sur le
    // parcours invite).
    loadingClassName?: string;
    loading?: 'lazy' | 'eager';
    'data-test'?: string;
};

type Status = 'loading' | 'loaded' | 'failed';

/**
 * Une image qui remplit son cadre sans apparaitre par morceaux : un reflet balaie le cadre pendant
 * le chargement, puis l'image se pose en fondu en se resserrant legerement. Si elle ne se charge
 * pas, le fond de repli reste a sa place plutot qu'une icone d'image cassee.
 *
 * Le cadre (position, dimensions, arrondis) appartient au parent, qui doit etre `relative`.
 * Reflet et entree s'animent en `transform` et `opacity` seulement ; sans mouvement, l'image
 * apparait directement une fois chargee.
 */
export function FadeInImage({
    src,
    alt,
    className,
    fallback = null,
    loadingClassName = 'bg-muted',
    loading = 'lazy',
    'data-test': dataTest,
}: Props) {
    const reduceMotion = useReducedMotion() === true;
    const [status, setStatus] = useState<Status>('loading');

    return (
        <>
            {status !== 'loaded' ? (
                <div
                    className={cn(
                        'absolute inset-0 overflow-hidden',
                        loadingClassName,
                    )}
                    aria-hidden="true"
                >
                    {status === 'failed' ? (
                        fallback
                    ) : reduceMotion ? null : (
                        <div className="image-shimmer absolute inset-0" />
                    )}
                </div>
            ) : null}

            {status !== 'failed' ? (
                <img
                    src={src}
                    alt={alt}
                    loading={loading}
                    decoding="async"
                    // Une image deja en cache peut etre complete avant que React ne branche
                    // `onLoad` : on la lit aussi au montage.
                    ref={(element) => {
                        if (element?.complete && element.naturalWidth > 0) {
                            setStatus('loaded');
                        }
                    }}
                    onLoad={() => setStatus('loaded')}
                    onError={() => setStatus('failed')}
                    className={cn(
                        'absolute inset-0 size-full object-cover',
                        !reduceMotion &&
                            'transition-[opacity,scale] duration-700 ease-out',
                        status === 'loaded'
                            ? 'scale-100 opacity-100'
                            : 'scale-[1.04] opacity-0',
                        className,
                    )}
                    data-test={dataTest}
                />
            ) : null}
        </>
    );
}
