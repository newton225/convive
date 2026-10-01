import { Link, router } from '@inertiajs/react';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { SubmitButton } from '@/components/submit-button';
import { BrandedTicket } from '@/components/ticket-template/branded-ticket';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useTranslation } from '@/hooks/use-translation';
import { Duration, EaseOut } from '@/lib/motion';
import { sampleTicket } from '@/lib/sample-tickets';
import type { SampleTicketHolder } from '@/lib/sample-tickets';
import { cn } from '@/lib/utils';
import { edit as organisationEdit } from '@/routes/tenants/organisation';
import type {
    TicketBrand,
    TicketCardEvent,
    TicketElements,
    TicketModel,
} from '@/types';

type Props = {
    tenantSlug: string;
    // La marque telle que le billet l'affichera, fonds compris.
    brand: TicketBrand;
    model: TicketModel;
    elements: TicketElements;
    previewEvent: TicketCardEvent;
    // Faux : modele et elements se lisent sans se modifier (permission absente, ou gabarit de
    // l'organisation montre sur un evenement qui n'a pas active le sien).
    editable: boolean;
    // Faux : aucun bouton d'enregistrement (permission absente).
    canSave: boolean;
    saveUrl: string;
    // Champs envoyes avec le gabarit, et ce qu'ils ont de non enregistre (activation par evenement).
    extraPayload?: Record<string, boolean>;
    extraDirty?: boolean;
    // Au-dessus des modeles : ce qui gouverne le gabarit (activation par evenement).
    intro?: ReactNode;
    // La carte des fonds du billet, dont le depot ne passe pas par l'enregistrement.
    backgrounds?: ReactNode;
};

const Models: TicketModel[] = ['classic', 'sober', 'elegant'];

const ElementKeys = ['logo', 'stamp', 'signature', 'companions'] as const;

const ElementFields: Record<(typeof ElementKeys)[number], string> = {
    logo: 'ticket_element_logo',
    stamp: 'ticket_element_stamp',
    signature: 'ticket_element_signature',
    companions: 'ticket_element_companions',
};

/**
 * L'editeur du gabarit du billet (README ecran 15) : modeles, fonds, elements activables et apercu
 * en direct. Le meme pour le gabarit de l'organisation et pour celui d'un evenement, qui l'emporte
 * quand il est active. L'etat repart des valeurs recues a chaque montage : la page qui change de
 * source (gabarit propre ou de l'organisation) lui donne une `key` differente.
 */
export function TicketTemplateEditor({
    tenantSlug,
    brand,
    model: initialModel,
    elements: initialElements,
    previewEvent,
    editable,
    canSave,
    saveUrl,
    extraPayload,
    extraDirty = false,
    intro,
    backgrounds,
}: Props) {
    const { t } = useTranslation();
    const [model, setModel] = useState<TicketModel>(initialModel);
    const [elements, setElements] = useState<TicketElements>(initialElements);
    const [processing, setProcessing] = useState(false);
    const [previewHolder, setPreviewHolder] =
        useState<SampleTicketHolder>('guest');
    // 1 vers l'accompagnateur, -1 vers l'invite principal : le sens du glissement ; 0 pour un
    // changement de modele, qui remplace le billet sur place.
    const [slideDirection, setSlideDirection] = useState(1);
    const reduceMotion = useReducedMotion() === true;
    const dirty =
        extraDirty ||
        model !== initialModel ||
        JSON.stringify(elements) !== JSON.stringify(initialElements);

    const save = () => {
        const payload: Record<string, boolean | string> = {
            ...extraPayload,
            ticket_model: model,
        };

        for (const key of ElementKeys) {
            payload[ElementFields[key]] = elements[key];
        }

        setProcessing(true);
        router.patch(saveUrl, payload, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    };

    const hasFile = (key: (typeof ElementKeys)[number]) =>
        key === 'logo'
            ? brand.logoUrl !== null
            : key === 'stamp'
              ? brand.stampUrl !== null
              : key === 'signature'
                ? brand.signatureUrl !== null
                : true;

    return (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <div className="space-y-6">
                {intro}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('ticket_template.models.title')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div
                            role="radiogroup"
                            aria-label={t('ticket_template.models.title')}
                            className="grid grid-cols-[repeat(auto-fit,minmax(8rem,1fr))] gap-2"
                        >
                            {Models.map((item) => (
                                <button
                                    key={item}
                                    type="button"
                                    role="radio"
                                    aria-checked={model === item}
                                    disabled={!editable}
                                    onClick={() => {
                                        if (item !== model) {
                                            setSlideDirection(0);
                                            setModel(item);
                                        }
                                    }}
                                    data-test={`ticket-model-${item}`}
                                    className={cn(
                                        'min-h-11 min-w-0 rounded-lg p-3 text-left text-sm break-words transition-colors focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60',
                                        'focus-visible:ring-ring',
                                        model === item
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted hover:bg-muted/70',
                                    )}
                                >
                                    <span className="block font-medium">
                                        {t(
                                            `ticket_template.models.${item}.label`,
                                        )}
                                    </span>
                                    <span className="block text-xs opacity-80">
                                        {t(
                                            `ticket_template.models.${item}.hint`,
                                        )}
                                    </span>
                                </button>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {backgrounds ? (
                    <Card data-test="ticket-background-card">
                        <CardContent className="space-y-6 pt-6">
                            {backgrounds}
                        </CardContent>
                    </Card>
                ) : null}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('ticket_template.elements.title')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {ElementKeys.map((key) => (
                            <div key={key} className="space-y-1">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id={`element-${key}`}
                                        checked={elements[key]}
                                        disabled={!editable}
                                        onCheckedChange={(checked) =>
                                            setElements((current) => ({
                                                ...current,
                                                [key]: checked === true,
                                            }))
                                        }
                                        data-test={`ticket-element-${key}`}
                                    />
                                    <Label htmlFor={`element-${key}`}>
                                        {t(`ticket_template.elements.${key}`)}
                                    </Label>
                                </div>
                                {elements[key] && !hasFile(key) ? (
                                    <p className="text-muted-foreground pl-6 text-xs">
                                        {t('ticket_template.elements.missing')}
                                    </p>
                                ) : null}
                            </div>
                        ))}
                        <Button variant="outline" size="sm" asChild>
                            <Link href={organisationEdit(tenantSlug)}>
                                {t('ticket_template.elements.brand_link')}
                            </Link>
                        </Button>
                    </CardContent>
                </Card>

                {canSave ? (
                    <SubmitButton
                        type="button"
                        processing={processing}
                        dirty={dirty}
                        onClick={save}
                        data-test="ticket-template-save"
                    >
                        {t('common.actions.save')}
                    </SubmitButton>
                ) : null}
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        {t('ticket_template.preview.title')}
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={previewHolder}
                        onValueChange={(next) => {
                            // Un clic sur le choix actif le viderait : on en garde un.
                            if (next === 'guest' || next === 'companion') {
                                setSlideDirection(
                                    next === 'companion' ? 1 : -1,
                                );
                                setPreviewHolder(next);
                            }
                        }}
                        className="w-full"
                        aria-label={t('ticket_template.preview.holder')}
                    >
                        <ToggleGroupItem
                            value="guest"
                            className="min-h-11 flex-1"
                            data-test="ticket-preview-guest"
                        >
                            {t('ticket_template.preview.holder_guest')}
                        </ToggleGroupItem>
                        <ToggleGroupItem
                            value="companion"
                            className="min-h-11 flex-1"
                            data-test="ticket-preview-companion"
                        >
                            {t('ticket_template.preview.holder_companion')}
                        </ToggleGroupItem>
                    </ToggleGroup>
                    {/* Le billet glisse dans le sens du choix, sur un fond gris fixe : vers la
                        gauche en allant a l'accompagnateur, vers la droite au retour. */}
                    <div className="bg-muted overflow-hidden rounded-xl px-3 py-8">
                        <AnimatePresence
                            mode="wait"
                            initial={false}
                            custom={slideDirection}
                        >
                            <motion.div
                                key={`${previewHolder}-${model}`}
                                custom={slideDirection}
                                variants={{
                                    // Sens 0 (changement de modele) : le billet se remplace sur
                                    // place, en fondu et leger zoom, sans glisser.
                                    enter: (direction: number) => ({
                                        opacity: 0,
                                        scale:
                                            reduceMotion || direction !== 0
                                                ? 1
                                                : 0.97,
                                        x: reduceMotion ? 0 : direction * 40,
                                    }),
                                    center: { opacity: 1, x: 0, scale: 1 },
                                    exit: (direction: number) => ({
                                        opacity: 0,
                                        scale:
                                            reduceMotion || direction !== 0
                                                ? 1
                                                : 1.02,
                                        x: reduceMotion ? 0 : direction * -40,
                                    }),
                                }}
                                initial="enter"
                                animate="center"
                                exit="exit"
                                transition={{
                                    duration: reduceMotion ? 0 : Duration.quick,
                                    ease: EaseOut,
                                }}
                            >
                                <BrandedTicket
                                    model={model}
                                    elements={elements}
                                    brand={brand}
                                    event={previewEvent}
                                    ticket={sampleTicket(
                                        previewHolder,
                                        elements.companions,
                                    )}
                                    showMissing
                                    withBackdrop={false}
                                />
                            </motion.div>
                        </AnimatePresence>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
