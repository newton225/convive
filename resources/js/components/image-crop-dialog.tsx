import { usePage } from '@inertiajs/react';
import { ZoomIn, ZoomOut } from 'lucide-react';
import { useEffect, useState } from 'react';
import Cropper from 'react-easy-crop';
import type { Area, Point } from 'react-easy-crop';
import { SubmitButton } from '@/components/submit-button';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import { documentCspNonce } from '@/lib/csp-nonce';

export type CropArea = { x: number; y: number; width: number; height: number };

type Props = {
    // Le fichier choisi, ouvert dans la fenetre tant qu'il n'est pas null.
    file: File | null;
    aspect: number;
    title: string;
    processing: boolean;
    onCancel: () => void;
    onConfirm: (area: CropArea) => void;
};

const MinZoom = 1;
const MaxZoom = 4;
const ZoomStep = 0.25;

/**
 * Rogner une image aux proportions imposees avant son envoi (fond du billet). Glisser pour
 * cadrer, pincer, molette ou boutons pour zoomer. Seule la zone part au serveur, en pixels de
 * l'image redressee : c'est lui qui decoupe et revalide (`SaveBrandFileRequest`), jamais une
 * image recadree par le navigateur.
 *
 * Les styles du composant passent par sa balise `<style>` portant le nonce du document : la CSP
 * refuse toute autre balise de style (SECURITY.md H7).
 */
export function ImageCropDialog({
    file,
    aspect,
    title,
    processing,
    onCancel,
    onConfirm,
}: Props) {
    const { t } = useTranslation();
    const { cspNonce } = usePage().props;
    const [crop, setCrop] = useState<Point>({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(MinZoom);
    const [area, setArea] = useState<Area | null>(null);
    const [source, setSource] = useState<string | null>(null);

    // L'URL de l'apercu nait et meurt dans le meme effet : rejoue en mode strict, il en recree
    // une plutot que de garder une adresse deja revoquee.
    useEffect(() => {
        setCrop({ x: 0, y: 0 });
        setZoom(MinZoom);
        setArea(null);

        if (file === null) {
            setSource(null);

            return;
        }

        const url = URL.createObjectURL(file);
        setSource(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    const changeZoom = (delta: number) =>
        setZoom((current) =>
            Math.min(MaxZoom, Math.max(MinZoom, current + delta)),
        );

    return (
        <Dialog
            open={file !== null}
            onOpenChange={(open) => !open && !processing && onCancel()}
        >
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>
                        {t('organisation.files.crop.description')}
                    </DialogDescription>
                </DialogHeader>

                <div
                    className="bg-muted relative aspect-[4/3] w-full overflow-hidden rounded-lg"
                    data-test="image-crop-area"
                >
                    {source ? (
                        <Cropper
                            image={source}
                            crop={crop}
                            zoom={zoom}
                            minZoom={MinZoom}
                            maxZoom={MaxZoom}
                            aspect={aspect}
                            onCropChange={setCrop}
                            onZoomChange={setZoom}
                            onCropComplete={(_, pixels) => setArea(pixels)}
                            nonce={documentCspNonce(cspNonce)}
                            showGrid
                        />
                    ) : null}
                </div>

                <div className="flex items-center justify-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="size-11"
                        aria-label={t('organisation.files.crop.zoom_out')}
                        disabled={zoom <= MinZoom}
                        onClick={() => changeZoom(-ZoomStep)}
                    >
                        <ZoomOut />
                    </Button>
                    <span className="text-muted-foreground w-14 text-center text-sm tabular-nums">
                        {Math.round(zoom * 100)} %
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="size-11"
                        aria-label={t('organisation.files.crop.zoom_in')}
                        disabled={zoom >= MaxZoom}
                        onClick={() => changeZoom(ZoomStep)}
                    >
                        <ZoomIn />
                    </Button>
                </div>

                <DialogFooter className="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        disabled={processing}
                        onClick={onCancel}
                    >
                        {t('common.actions.cancel')}
                    </Button>
                    <SubmitButton
                        type="button"
                        processing={processing}
                        disabled={area === null}
                        data-test="image-crop-confirm"
                        onClick={() => {
                            if (area) {
                                onConfirm({
                                    x: Math.round(area.x),
                                    y: Math.round(area.y),
                                    width: Math.round(area.width),
                                    height: Math.round(area.height),
                                });
                            }
                        }}
                    >
                        {t('organisation.files.crop.confirm')}
                    </SubmitButton>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
