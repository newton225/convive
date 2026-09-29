import { CreditCard, FileText, Ticket } from 'lucide-react';
import { Reveal } from '@/components/site/reveal';
import { useTranslation } from '@/hooks/use-translation';

const Steps = [
    { key: 'form', icon: FileText },
    { key: 'pay', icon: CreditCard },
    { key: 'ticket', icon: Ticket },
] as const;

/**
 * Les trois temps de l'inscription, annonces avant qu'elle commence : l'invite sait ou il va, y
 * compris l'etape qui se passe hors de l'application (le versement chez l'organisateur).
 * CLAUDE.md, « Un ecran, une decision » : le parcours est sequence en etapes numerotees.
 */
export function EventHowItWorks() {
    const { t } = useTranslation();

    return (
        <section className="space-y-4">
            <h2 className="text-lg font-semibold">
                {t('guest.event.how.title')}
            </h2>
            <ol className="space-y-3">
                {Steps.map((step, index) => (
                    <li key={step.key}>
                        <Reveal
                            delay={index * 0.08}
                            className="bg-card flex items-start gap-4 rounded-2xl p-4"
                        >
                            <span className="brand-fill flex size-10 shrink-0 items-center justify-center rounded-xl text-white">
                                <step.icon className="size-5" />
                            </span>
                            <span>
                                <span className="text-muted-foreground block text-xs font-semibold tabular-nums">
                                    {String(index + 1).padStart(2, '0')}
                                </span>
                                <span className="block font-semibold">
                                    {t(`guest.event.how.${step.key}.title`)}
                                </span>
                                <span className="text-muted-foreground block text-sm">
                                    {t(`guest.event.how.${step.key}.body`)}
                                </span>
                            </span>
                        </Reveal>
                    </li>
                ))}
            </ol>
        </section>
    );
}
