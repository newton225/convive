import { useReducedMotion } from 'framer-motion';
import gsap from 'gsap';
import { CircleCheck, ScanLine, Timer } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef } from 'react';
import { formatCountdown } from '@/hooks/use-countdown';
import { useTranslation } from '@/hooks/use-translation';
import { TicketPreview } from './ticket-preview';

// Pas de mise en page cote serveur : `useLayoutEffect` y avertirait, `useEffect` n'y court pas.
const useIsomorphicLayoutEffect =
    typeof window === 'undefined' ? useEffect : useLayoutEffect;

const HoldSeconds = 582;

/**
 * La scene de l'accroche : le billet au centre, et autour de lui les trois temps du produit qui
 * s'enchainent dans l'ordre ou ils arrivent a l'invite. La reservation decompte, la preuve est
 * validee et le billet passe de « en attente » a « valide », puis l'entree est acceptee au scan. Le
 * mouvement raconte la causalite (CLAUDE.md, « Le produit comme heros »).
 *
 * Une seule ligne de temps GSAP orchestre tout, en boucle : le billet entre une fois, puis la
 * sequence se joue, se range et recommence. Seuls `transform` et `opacity` bougent. Elle s'arrete
 * hors de l'ecran, et sans mouvement (`prefers-reduced-motion`) la scene reste dans son etat final.
 */
export function HeroStage() {
    const { t } = useTranslation();
    const reduceMotion = useReducedMotion() === true;
    const root = useRef<HTMLDivElement>(null);
    const countdown = useRef<HTMLSpanElement>(null);

    useIsomorphicLayoutEffect(() => {
        const scope = root.current;

        if (reduceMotion || !scope) {
            return;
        }

        const context = gsap.context(() => {
            const q = gsap.utils.selector(scope);
            const stage = (name: string) => q(`[data-stage="${name}"]`);
            const counter = { value: HoldSeconds };
            // Le texte ne change qu'une fois par seconde : le reecrire a chaque image repeindrait la
            // pastille soixante fois par seconde pour rien.
            let shown = -1;
            const render = () => {
                const seconds = Math.round(counter.value);

                if (countdown.current && seconds !== shown) {
                    shown = seconds;
                    countdown.current.textContent = formatCountdown(seconds);
                }
            };

            // L'etat de depart, pose avant la premiere image : le billet attend sa preuve.
            gsap.set(stage('ticket'), { autoAlpha: 0, y: 36, scale: 0.97 });
            gsap.set(stage('hold'), { autoAlpha: 0, x: -24 });
            gsap.set(stage('proof'), { autoAlpha: 0, y: 24, scale: 0.96 });
            gsap.set(stage('scan'), { autoAlpha: 0, x: 24 });
            gsap.set(stage('pending'), { autoAlpha: 1, y: 0 });
            gsap.set(stage('valid'), { autoAlpha: 0, y: 6 });
            gsap.set(stage('qr-cell'), {
                autoAlpha: 0.1,
                scale: 0.6,
                transformBox: 'fill-box',
                transformOrigin: '50% 50%',
            });
            gsap.set(stage('scanline'), { autoAlpha: 0, y: 0 });
            gsap.set(stage('scan-idle'), { autoAlpha: 1 });
            gsap.set(stage('scan-done'), { autoAlpha: 0, y: 6 });
            render();

            const intro = gsap.timeline({ defaults: { ease: 'expo.out' } });

            intro.to(
                stage('ticket'),
                { autoAlpha: 1, y: 0, scale: 1, duration: 1.1 },
                0.1,
            );

            const loop = gsap.timeline({
                repeat: -1,
                repeatDelay: 0.5,
                defaults: { ease: 'power3.out' },
            });

            // 1. La reservation court : le decompte descend vraiment, sans passer par React.
            loop.to(
                stage('hold'),
                { autoAlpha: 1, x: 0, duration: 0.8 },
                0.2,
            ).to(
                counter,
                {
                    value: HoldSeconds - 9,
                    duration: 3.4,
                    ease: 'none',
                    onUpdate: render,
                },
                0.4,
            );

            // 2. La preuve est validee : la notification arrive, la coche s'imprime, la reservation
            // s'efface, et le billet passe de « en attente » a « valide ».
            loop.to(
                stage('proof'),
                { autoAlpha: 1, y: 0, scale: 1, duration: 0.8 },
                3.0,
            )
                .fromTo(
                    stage('proof-check'),
                    { scale: 0 },
                    { scale: 1, duration: 0.6, ease: 'back.out(2.4)' },
                    3.25,
                )
                .to(stage('hold'), { autoAlpha: 0, x: -14, duration: 0.5 }, 3.7)
                .to(
                    stage('pending'),
                    { autoAlpha: 0, y: -6, duration: 0.35 },
                    3.55,
                )
                .to(stage('valid'), { autoAlpha: 1, y: 0, duration: 0.5 }, 3.65)
                .to(
                    stage('qr-cell'),
                    {
                        autoAlpha: 1,
                        scale: 1,
                        duration: 0.45,
                        ease: 'back.out(1.7)',
                        stagger: { each: 0.03, from: 'random' },
                    },
                    3.65,
                );

            // 3. A la porte : le scan balaie le QR, l'entree est acceptee.
            loop.to(stage('scan'), { autoAlpha: 1, x: 0, duration: 0.8 }, 5.4)
                .fromTo(
                    stage('scanline'),
                    { autoAlpha: 1, y: 0 },
                    {
                        y: 72,
                        duration: 1,
                        ease: 'power1.inOut',
                    },
                    6.0,
                )
                .to(stage('scanline'), { autoAlpha: 0, duration: 0.2 }, 6.85)
                .to(stage('scan-idle'), { autoAlpha: 0, duration: 0.2 }, 6.9)
                .to(
                    stage('scan-done'),
                    { autoAlpha: 1, y: 0, duration: 0.45 },
                    7.0,
                )
                .fromTo(
                    stage('scan-check'),
                    { scale: 0 },
                    { scale: 1, duration: 0.5, ease: 'back.out(2.6)' },
                    7.05,
                )
                .fromTo(
                    stage('valid'),
                    { scale: 1 },
                    {
                        scale: 1.08,
                        duration: 0.2,
                        yoyo: true,
                        repeat: 1,
                        ease: 'sine.inOut',
                    },
                    7.05,
                );

            // 4. Tout se range avant de recommencer : la boucle ne saute jamais.
            loop.to(
                [...stage('proof'), ...stage('scan')],
                { autoAlpha: 0, y: 10, duration: 0.5, ease: 'power2.in' },
                9.4,
            )
                .to(
                    stage('qr-cell'),
                    {
                        autoAlpha: 0.1,
                        scale: 0.6,
                        duration: 0.35,
                        ease: 'power2.in',
                        stagger: { each: 0.01, from: 'random' },
                    },
                    9.4,
                )
                .to(stage('valid'), { autoAlpha: 0, y: 6, duration: 0.3 }, 9.6)
                .to(
                    stage('pending'),
                    { autoAlpha: 1, y: 0, duration: 0.4 },
                    9.8,
                )
                .set(stage('scan-idle'), { autoAlpha: 1 }, 10.1)
                .set(stage('scan-done'), { autoAlpha: 0, y: 6 }, 10.1)
                .set(stage('scan'), { x: 24 }, 10.1)
                .set(stage('hold'), { x: -24 }, 10.1)
                .call(
                    () => {
                        counter.value = HoldSeconds;
                        render();
                    },
                    undefined,
                    10.1,
                );

            const sequence = gsap.timeline();

            sequence.add(intro).add(loop);

            // Un billet qui flotte a peine : la scene vit sans distraire.
            // L'ombre au sol respire avec lui : plus le billet monte, plus elle se resserre et
            // s'eclaircit, ce qui donne la hauteur de la levitation.
            const float = gsap.timeline({
                repeat: -1,
                yoyo: true,
                defaults: { duration: 3.6, ease: 'sine.inOut' },
            });

            float
                .to(
                    stage('float'),
                    // Reste sur sa propre couche GPU : le navigateur interpole la position au
                    // sous-pixel au lieu de re-dessiner le billet, ce qui evitait des saccades.
                    { y: -10, force3D: true },
                    0,
                )
                .to(stage('shadow'), { scaleX: 0.84, opacity: 0.45 }, 0);

            // Hors de l'ecran, tout s'arrete : rien ne tourne pour rien.
            const observer = new IntersectionObserver(([entry]) => {
                const visible = entry?.isIntersecting ?? true;

                sequence.paused(!visible);
                float.paused(!visible);
            });

            observer.observe(scope);

            // L'inclinaison 3D : le billet se penche vers le pointeur, comme s'il etait pousse par lui,
            // et un reflet suit la souris. `quickTo` lisse chaque mouvement sur une courte duree : le
            // billet ne saute jamais d'une position a l'autre. Souris et stylet seulement, un doigt qui
            // defile ne doit rien incliner.
            const tilt = scope.querySelector<HTMLElement>(
                '[data-stage="tilt"]',
            );
            const glare = scope.querySelector<HTMLElement>(
                '[data-stage="glare"]',
            );
            const area = scope.querySelector<HTMLElement>(
                '[data-stage="float"]',
            );
            const cleanups: Array<() => void> = [() => observer.disconnect()];

            if (tilt && glare && area) {
                const MaxTilt = 11;
                // La pose de repos : le billet est pose un peu de travers, vers la gauche.
                const RestRotation = -2;
                const smooth = { duration: 0.7, ease: 'power3.out' };

                gsap.set(tilt, {
                    transformPerspective: 900,
                    rotationZ: RestRotation,
                });

                const rotateX = gsap.quickTo(tilt, 'rotationX', smooth);
                const rotateY = gsap.quickTo(tilt, 'rotationY', smooth);
                const scale = gsap.quickTo(tilt, 'scale', {
                    duration: 0.5,
                    ease: 'power3.out',
                });
                const shine = gsap.quickTo(glare, 'opacity', {
                    duration: 0.5,
                    ease: 'power2.out',
                });

                const follow = (event: PointerEvent) => {
                    if (event.pointerType === 'touch') {
                        return;
                    }

                    const box = area.getBoundingClientRect();
                    const x = Math.min(
                        1,
                        Math.max(0, (event.clientX - box.left) / box.width),
                    );
                    const y = Math.min(
                        1,
                        Math.max(0, (event.clientY - box.top) / box.height),
                    );

                    rotateY((x - 0.5) * 2 * MaxTilt);
                    rotateX(-(y - 0.5) * 2 * MaxTilt);
                    scale(1.03);
                    glare.style.setProperty('--glare-x', `${x * 100}%`);
                    glare.style.setProperty('--glare-y', `${y * 100}%`);
                    shine(1);
                };

                const rest = () => {
                    rotateX(0);
                    rotateY(0);
                    scale(1);
                    shine(0);
                };

                area.addEventListener('pointermove', follow);
                area.addEventListener('pointerleave', rest);
                cleanups.push(() => {
                    area.removeEventListener('pointermove', follow);
                    area.removeEventListener('pointerleave', rest);
                });
            }

            return () => cleanups.forEach((cleanup) => cleanup());
        }, scope);

        return () => context.revert();
    }, [reduceMotion]);

    return (
        <div
            ref={root}
            className="relative mx-auto w-full max-w-md pt-16 pb-20 lg:pt-20 lg:pb-24"
        >
            <div
                data-stage="ticket"
                className="relative z-10 flex justify-center"
            >
                {/* L'ombre reste au sol, derriere le billet : un halo sombre, et dessous une lueur indigo
                    qui se lit sur le fond encre ou le noir seul ne se verrait pas. */}
                <span
                    data-stage="shadow"
                    className="pointer-events-none absolute inset-x-[10%] -bottom-7 h-10 rounded-[50%] bg-[radial-gradient(closest-side,oklch(0_0_0/0.6),oklch(0.52_0.13_262/0.22)_60%,transparent)]"
                    aria-hidden="true"
                />
                <div
                    data-stage="float"
                    className="w-full max-w-sm will-change-transform"
                >
                    {/* L'inclinaison suit le pointeur : un plan a part, pour ne pas se disputer le
                        mouvement de levitation du parent. */}
                    <div
                        data-stage="tilt"
                        className="relative [transform:rotate(-2deg)] [transform-style:preserve-3d]"
                    >
                        <TicketPreview />
                        <span
                            data-stage="glare"
                            className="site-glare pointer-events-none absolute inset-0 rounded-2xl opacity-0"
                            aria-hidden="true"
                        />
                    </div>
                </div>
            </div>

            <div
                data-stage="hold"
                className="bg-ink/90 absolute top-4 left-0 z-20 flex items-center gap-3 rounded-2xl border border-white/10 px-3.5 py-2.5 text-white ring-1 ring-white/5 sm:-left-8 lg:top-6"
                data-test="site-hero-hold"
            >
                <span className="bg-primary/25 text-primary-foreground flex size-8 items-center justify-center rounded-lg">
                    <Timer className="size-4" />
                </span>
                <span>
                    <span className="block text-xs text-white/60">
                        {t('site.hero.stage.hold')}
                    </span>
                    <span
                        ref={countdown}
                        className="block font-semibold tabular-nums"
                    >
                        {formatCountdown(HoldSeconds)}
                    </span>
                </span>
            </div>

            <div
                data-stage="proof"
                className="bg-card text-card-foreground absolute right-0 bottom-4 z-20 flex max-w-[16rem] items-start gap-3 rounded-2xl border border-white/10 px-3.5 py-3 ring-1 ring-white/5 sm:-right-6 lg:bottom-6"
                role="status"
                data-test="site-hero-proof"
            >
                <CircleCheck
                    data-stage="proof-check"
                    className="text-primary mt-0.5 size-5 shrink-0"
                />
                <span>
                    <span className="block text-sm font-semibold">
                        {t('site.hero.stage.proof_title')}
                    </span>
                    <span className="text-muted-foreground block text-xs">
                        {t('site.hero.stage.proof_body')}
                    </span>
                </span>
            </div>

            <div
                data-stage="scan"
                className="bg-ink/90 absolute top-4 right-0 z-20 hidden items-center rounded-full border border-white/10 px-3.5 py-1.5 text-xs whitespace-nowrap text-white ring-1 ring-white/5 sm:grid lg:top-6 lg:-right-6"
            >
                <span
                    data-stage="scan-idle"
                    className="flex items-center gap-2 text-white/70 opacity-0 [grid-area:1/1]"
                >
                    <ScanLine className="size-3.5" />
                    {t('site.hero.stage.scanning')}
                </span>
                <span
                    data-stage="scan-done"
                    className="flex items-center gap-2 [grid-area:1/1]"
                >
                    <CircleCheck
                        data-stage="scan-check"
                        className="text-primary-foreground size-3.5"
                    />
                    {t('site.hero.stage.scan')}
                </span>
            </div>
        </div>
    );
}
