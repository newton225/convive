import { motion, useReducedMotion } from 'framer-motion';
import type { ReactNode } from 'react';

type Props = {
    children: ReactNode;
    delay?: number;
    className?: string;
};

/**
 * Une entree douce (opacite et translation, 0.5 s) qui se joue une fois, a l'arrivee dans l'ecran.
 * Le mouvement explique l'ordre de lecture, il ne decore pas : avec `prefers-reduced-motion`, le
 * contenu s'affiche tel quel, sans aucune animation, et le texte reste toujours selectionnable.
 */
export function Reveal({ children, delay = 0, className }: Props) {
    const reduceMotion = useReducedMotion();

    if (reduceMotion) {
        return <div className={className}>{children}</div>;
    }

    return (
        <motion.div
            className={className}
            initial={{ opacity: 0, y: 16 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true, margin: '-60px' }}
            transition={{ duration: 0.5, delay, ease: 'easeOut' }}
        >
            {children}
        </motion.div>
    );
}
