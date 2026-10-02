import { Head, router } from '@inertiajs/react';
import { BrowserQRCodeReader, type IScannerControls } from '@zxing/browser';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import { ProductTourButton } from '@/components/product-tour-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { SubmitButton } from '@/components/submit-button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { InstallPrompt } from '@/components/install-prompt';
import { GuestLookupCard } from '@/components/scan/guest-lookup-card';
import {
    OfflineScanPanel,
    type LocalScanKind,
} from '@/components/scan/offline-scan-panel';
import { RotateTicketKeyCard } from '@/components/scan/rotate-ticket-key-card';
import { ScanEventBanner } from '@/components/scan/scan-event-banner';
import { ScanLockOverlay } from '@/components/scan/scan-lock-overlay';
import { ScanPinForm } from '@/components/scan/scan-pin-form';
import { ScanViewfinderStatus } from '@/components/scan/scan-viewfinder-status';
import type { ViewfinderPhase } from '@/components/scan/scan-viewfinder-status';
import { useOnlineStatus } from '@/hooks/use-online-status';
import { useScanLock } from '@/hooks/use-scan-lock';
import { useScanQueue, type SyncOutcome } from '@/hooks/use-scan-queue';
import { translate, useTranslation } from '@/hooks/use-translation';
import {
    clearEventScanStorage,
    hasSeenLocally,
    markSeenLocally,
    readStoredRevocationList,
    storeRevocationList,
} from '@/lib/scan-queue';
import {
    verifyRevocationList,
    verifyTicketOffline,
    type ExpectedEvent,
} from '@/lib/ticket-verifier';
import { rememberEntryControl } from '@/lib/entry-control-memory';
import { formatDateTime } from '@/lib/format-date';
import { offlineVerdict, onlineVerdict, vibrateFor } from '@/lib/scan-verdict';
import type { ScanVerdict } from '@/lib/scan-verdict';
import type { ScanPinVerifier } from '@/lib/scan-pin';
import { readStation, writeStation } from '@/lib/scan-station';
import { can, Permission } from '@/lib/permissions';
import { index as eventsIndex } from '@/routes/tenants/events';
import { admit, index, verify } from '@/routes/tenants/events/scan';
import type {
    ScanEventProps,
    ScanLookup,
    ScanOutcome,
    ScanRecentRow,
    ScanSameDayEvent,
    TenantPermissions,
    Translations,
} from '@/types';

type Props = {
    tenant: { slug: string };
    tenantId: number;
    event: ScanEventProps;
    revocationList: string | null;
    canRotateKey: boolean;
    scanPin: ScanPinVerifier | null;
    permissions: TenantPermissions;
    recent: ScanRecentRow[];
    acceptedCount: number;
    expectedCount: number;
    otherEventsToday: ScanSameDayEvent[];
    // Verdict du scan qui vient d'avoir lieu, null a l'arrivee sur l'ecran.
    result?: ScanOutcome | null;
    // Recherche d'un invite dont le QR ne peut pas etre lu, null tant que rien n'a ete cherche.
    lookup?: ScanLookup | null;
};

// Lit le verdict du serveur dans les props d'une reponse, ou `failed` quand il n'y en a pas.
// Duree d'affichage du verdict sur la camera : le temps de le lire en regardant l'invite.
const VerdictDisplayMs = 2800;

const outcomeOf = (props: unknown): SyncOutcome => {
    if (typeof props === 'object' && props !== null && 'result' in props) {
        const outcome = props.result;

        if (
            typeof outcome === 'object' &&
            outcome !== null &&
            'result' in outcome
        ) {
            const value = outcome.result;

            if (
                value === 'accepted' ||
                value === 'already_scanned' ||
                value === 'refused'
            ) {
                return value;
            }
        }
    }

    return 'failed';
};

/**
 * README ecran 26 : le controle a l'entree, etape 7 de « Ordre de construction ». Une visite
 * Inertia par code lu (rechargement partiel, `only`), pas une navigation complete : la camera
 * reste active d'un scan a l'autre.
 *
 * Hors ligne (CLAUDE.md, pile PWA) : le billet est verifie sur l'appareil avec la cle publique de
 * l'evenement (`verifyTicketOffline`), le passage est mis en file locale puis rejoue un par un au
 * retour du reseau. Le serveur reste juge : un billet accepte hors ligne qu'il refuse est signale
 * « a revoir ». Limite connue : l'heure enregistree est celle de la synchronisation, pas du scan.
 */
export default function EventScan({
    tenant,
    tenantId,
    event,
    revocationList,
    canRotateKey,
    scanPin,
    permissions,
    recent,
    acceptedCount,
    expectedCount,
    otherEventsToday,
    result,
    lookup,
}: Props) {
    const { t, locale } = useTranslation();

    // Ouvrir ce scan, c'est avoir choisi son controle du jour : le raccourci du menu y ramenera
    // ce telephone jusqu'au soir (README ecran 26, deux evenements le meme jour).
    useEffect(() => {
        rememberEntryControl(tenant.slug, event.id);
    }, [tenant.slug, event.id]);
    const videoRef = useRef<HTMLVideoElement>(null);
    const controlsRef = useRef<IScannerControls | null>(null);
    const lastTokenRef = useRef<string | null>(null);
    const [cameraError, setCameraError] = useState(false);
    const [cameraReady, setCameraReady] = useState(false);
    const [pending, setPending] = useState(false);
    // Verdict affiche quelques secondes par-dessus la camera ; `key` rejoue l'apparition meme
    // quand deux billets de suite donnent le meme verdict.
    const [verdict, setVerdict] = useState<{
        value: ScanVerdict;
        key: number;
    } | null>(null);
    const verdictTimerRef = useRef<number | null>(null);

    function showVerdict(value: ScanVerdict) {
        vibrateFor(value.tone);
        setVerdict({ value, key: Date.now() });

        if (verdictTimerRef.current !== null) {
            window.clearTimeout(verdictTimerRef.current);
        }

        verdictTimerRef.current = window.setTimeout(
            () => setVerdict(null),
            VerdictDisplayMs,
        );
    }

    useEffect(
        () => () => {
            if (verdictTimerRef.current !== null) {
                window.clearTimeout(verdictTimerRef.current);
            }
        },
        [],
    );
    const [station, setStation] = useState(readStation);
    // Le resultat reste affiche jusqu'a « Scanner suivant » (prototype) ou jusqu'au scan suivant.
    const [resultDismissed, setResultDismissed] = useState(false);
    const canForce = can(permissions, Permission.ScanForce);
    const canAdmitWithoutScan = can(permissions, Permission.ScanManual);
    const online = useOnlineStatus();
    const lock = useScanLock(scanPin);
    // La camera tourne en continu : sans code choisi ou ecran verrouille, les billets lus sont
    // ignores (SECURITY.md M8). Des refs, parce que le lecteur est installe une seule fois.
    const scanBlockedRef = useRef(scanPin === null || lock.locked);
    const registerActivityRef = useRef(lock.registerActivity);

    useEffect(() => {
        scanBlockedRef.current = scanPin === null || lock.locked;
        registerActivityRef.current = lock.registerActivity;
    }, [scanPin, lock.locked, lock.registerActivity]);
    const [localResult, setLocalResult] = useState<LocalScanKind | null>(null);
    const expected: ExpectedEvent = {
        tenantId,
        eventId: event.id,
        keyVersion: event.qrKeyVersion,
        validUntil: event.ticketValidUntil,
    };

    // Garde la derniere liste de revocation authentique recue (SECURITY.md C2) : c'est elle que
    // le scan hors ligne consultera jusqu'a la prochaine synchronisation.
    useEffect(() => {
        void verifyRevocationList(
            revocationList,
            event.qrPublicKey,
            expected,
        ).then((revoked) => {
            if (revoked !== null && revocationList !== null) {
                storeRevocationList(event.id, revocationList);
            }
        });
        // `expected` est derive des props deja listees.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [revocationList, event.qrPublicKey, event.qrKeyVersion]);

    // Rejoue un scan de la file aupres du serveur et rend son verdict ; tout echec le laisse en file.
    const syncOne = (token: string) =>
        new Promise<SyncOutcome>((resolve) => {
            router.post(
                verify([tenant.slug, event.id]).url,
                { token, force: false, station },
                {
                    preserveScroll: true,
                    preserveState: true,
                    only: ['result', 'recent', 'acceptedCount'],
                    onSuccess: (page) => resolve(outcomeOf(page.props)),
                    onError: () => resolve('failed'),
                    onCancel: () => resolve('failed'),
                },
            );
        });

    const { queued, syncing, review, enqueue, sync, dismissReview } =
        useScanQueue(event.id, syncOne);

    useEffect(() => {
        if (event.closed) {
            clearEventScanStorage(event.id);
        }
    }, [event.closed, event.id]);

    const wasOnlineRef = useRef(online);

    useEffect(() => {
        if (online && !wasOnlineRef.current) {
            // Retour du reseau : nouvelle cle et liste de revocation a jour. Visite asynchrone,
            // pour ne pas annuler les envois de la file rejoues en meme temps.
            router.reload({ only: ['event', 'revocationList'], async: true });
        }

        wasOnlineRef.current = online;

        if (online && queued > 0) {
            void sync();
        }
        // Au retour du reseau seulement : `sync` et `queued` changent a chaque scan.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [online]);

    async function scanOffline(token: string) {
        lastTokenRef.current = token;
        setTimeout(() => {
            if (lastTokenRef.current === token) {
                lastTokenRef.current = null;
            }
        }, 3000);

        const revoked =
            (await verifyRevocationList(
                readStoredRevocationList(event.id),
                event.qrPublicKey,
                expected,
            )) ?? [];

        const verification = await verifyTicketOffline(
            token,
            event.qrPublicKey,
            expected,
            revoked,
        );

        if (verification.status !== 'valid') {
            setLocalResult(verification.status);
            showVerdict(offlineVerdict(verification.status));

            return;
        }

        if (
            hasSeenLocally(
                event.id,
                verification.registrationId,
                verification.holder,
            )
        ) {
            setLocalResult('already_local');
            showVerdict(offlineVerdict('already_local'));

            return;
        }

        markSeenLocally(
            event.id,
            verification.registrationId,
            verification.holder,
        );
        enqueue(token);
        setLocalResult('verified');
        showVerdict(offlineVerdict('verified'));
    }

    function handleToken(token: string) {
        if (online) {
            setLocalResult(null);
            submit(token);

            return;
        }

        void scanOffline(token);
    }

    function submit(token: string, force = false) {
        setPending(true);
        lastTokenRef.current = token;

        router.post(
            verify([tenant.slug, event.id]).url,
            { token, force, station },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['result', 'recent', 'acceptedCount'],
                onSuccess: (page) => {
                    setResultDismissed(false);

                    const outcome = outcomeOf(page.props);

                    if (outcome !== 'failed') {
                        showVerdict(onlineVerdict(outcome));
                    }
                },
                onFinish: () => {
                    setPending(false);
                    // Laisse la meme presentation valoir « deja scanne » pendant un court
                    // instant plutot que de la resoumettre en boucle tant qu'elle reste dans
                    // le champ de la camera.
                    setTimeout(() => {
                        if (lastTokenRef.current === token) {
                            lastTokenRef.current = null;
                        }
                    }, 3000);
                },
            },
        );
    }

    // Entree sans scan : le billet a ete retrouve par la recherche, pas lu par la camera.
    function admitWithoutScan(
        ticketId: number,
        force: boolean,
        done: () => void,
    ) {
        setPending(true);
        setLocalResult(null);
        // « Forcer l'entree » du resultat rejoue le dernier code lu : il ne doit pas viser un
        // billet scanne plus tot.
        lastTokenRef.current = null;

        router.post(
            admit([tenant.slug, event.id]).url,
            { ticket: ticketId, force, station },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['result', 'recent', 'acceptedCount', 'lookup'],
                onSuccess: (page) => {
                    setResultDismissed(false);

                    const outcome = outcomeOf(page.props);

                    if (outcome !== 'failed') {
                        showVerdict(onlineVerdict(outcome));
                    }

                    done();
                },
                onFinish: () => setPending(false),
            },
        );
    }

    // La camera s'ouvre de facon asynchrone : on ne recoit de quoi l'arreter qu'une fois qu'elle a
    // repondu. Si l'ecran est quitte avant (React monte deux fois en developpement, l'agent peut
    // repartir vite), le nettoyage ne trouvait rien a arreter et la camera restait allumee, voyant
    // compris, apres le depart de la page. Deux garde-fous : une camera qui repond trop tard est
    // coupee aussitot, et au depart toutes les pistes du flux video sont arretees.
    useEffect(() => {
        let cancelled = false;
        const reader = new BrowserQRCodeReader();
        const video = videoRef.current;

        const stopTracks = () => {
            const stream = video?.srcObject;

            if (stream instanceof MediaStream) {
                stream.getTracks().forEach((track) => track.stop());
            }

            if (video) {
                video.srcObject = null;
            }
        };

        reader
            .decodeFromVideoDevice(undefined, video ?? undefined, (decoded) => {
                if (cancelled || !decoded || scanBlockedRef.current) {
                    return;
                }

                registerActivityRef.current();

                const text = decoded.getText();

                if (text === lastTokenRef.current) {
                    return;
                }

                handleToken(text);
            })
            .then((controls) => {
                if (cancelled) {
                    controls.stop();
                    stopTracks();

                    return;
                }

                controlsRef.current = controls;
                setCameraReady(true);
            })
            .catch(() => {
                if (!cancelled) {
                    setCameraError(true);
                }
            });

        return () => {
            cancelled = true;
            controlsRef.current?.stop();
            controlsRef.current = null;
            stopTracks();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const viewfinderPhase: ViewfinderPhase = cameraError
        ? 'error'
        : !cameraReady
          ? 'starting'
          : scanPin === null || lock.locked
            ? 'paused'
            : pending
              ? 'checking'
              : 'searching';

    return (
        <>
            <Head title={t('scan.title')} />

            <div className="flex flex-col space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading variant="small" title={t('scan.title')} />
                    <ProductTourButton
                        tour="entry_control"
                        autoStart={!lock.locked}
                    />
                </div>

                <ScanEventBanner
                    tenantSlug={tenant.slug}
                    event={event}
                    otherEventsToday={otherEventsToday}
                />

                <InstallPrompt />

                {scanPin === null ? (
                    <Card data-test="scan-pin-setup" data-tour="scan-pin">
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('scan.pin_setup.title')}
                            </CardTitle>
                            <p className="text-muted-foreground text-sm">
                                {t('scan.pin_setup.description')}
                            </p>
                        </CardHeader>
                        <CardContent>
                            <ScanPinForm
                                submitLabel={t('account.scan_pin.create')}
                            />
                        </CardContent>
                    </Card>
                ) : null}

                {lock.locked ? (
                    <ScanLockOverlay
                        attemptsLeft={lock.attemptsLeft}
                        online={online}
                        onUnlock={lock.unlock}
                    />
                ) : null}

                <div className="grid gap-6 lg:grid-cols-[320px_1fr]">
                    <Card>
                        <CardContent className="space-y-4 pt-6">
                            <div
                                className="bg-muted relative aspect-square overflow-hidden rounded-lg"
                                data-tour="scan-viewfinder"
                            >
                                <ScanViewfinderStatus
                                    phase={viewfinderPhase}
                                    verdict={verdict?.value ?? null}
                                    verdictKey={verdict?.key ?? 0}
                                />
                                <video
                                    ref={videoRef}
                                    className="h-full w-full object-cover"
                                    data-test="scan-video"
                                    muted
                                    playsInline
                                />
                            </div>

                            {cameraError ? (
                                <p
                                    className="text-destructive text-center text-sm"
                                    data-test="scan-camera-error"
                                >
                                    {t('scan.viewfinder.camera_denied')}
                                </p>
                            ) : (
                                <p className="text-muted-foreground text-center text-sm">
                                    {pending
                                        ? t('scan.viewfinder.scanning')
                                        : t('scan.viewfinder.placeholder')}
                                </p>
                            )}

                            <div
                                className="grid gap-2"
                                data-tour="scan-station"
                            >
                                <Label htmlFor="scan-station">
                                    {t('scan.station.label')}
                                </Label>
                                <Input
                                    id="scan-station"
                                    name="station"
                                    value={station}
                                    maxLength={60}
                                    placeholder={t('scan.station.placeholder')}
                                    onChange={(changeEvent) => {
                                        setStation(changeEvent.target.value);
                                        writeStation(changeEvent.target.value);
                                    }}
                                    data-test="scan-station"
                                />
                            </div>

                            <div
                                className="text-center"
                                data-tour="scan-counter"
                            >
                                <p
                                    className="text-3xl font-semibold tabular-nums"
                                    data-test="scan-counter"
                                >
                                    {t('scan.counter.value', {
                                        entered: acceptedCount,
                                        expected: expectedCount,
                                    })}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    {t('scan.counter.label')}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="space-y-6">
                        <OfflineScanPanel
                            online={online}
                            queued={queued}
                            syncing={syncing}
                            review={review}
                            localResult={localResult}
                            onSync={() => void sync()}
                            onDismissReview={dismissReview}
                        />

                        {result && !resultDismissed ? (
                            <Card
                                data-test="scan-result"
                                data-result={result.result}
                            >
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        {t(`scan.results.${result.result}`)}
                                        {result.forced ? (
                                            <Badge variant="secondary">
                                                {t('scan.result.forced_badge')}
                                            </Badge>
                                        ) : null}
                                        {result.manual ? (
                                            <Badge
                                                variant="outline"
                                                data-test="scan-result-manual"
                                            >
                                                {t('scan.result.manual_badge')}
                                            </Badge>
                                        ) : null}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    {result.registration ? (
                                        <>
                                            <p
                                                className="font-medium"
                                                data-test="scan-result-name"
                                            >
                                                {result.registration.name}
                                            </p>
                                            <p className="text-muted-foreground text-sm">
                                                {result.registration.unit}
                                            </p>
                                            {result.registration.guestOf ? (
                                                <p
                                                    className="text-muted-foreground text-sm"
                                                    data-test="scan-result-guest-of"
                                                >
                                                    {t('scan.result.guest_of', {
                                                        name: result
                                                            .registration
                                                            .guestOf,
                                                    })}
                                                </p>
                                            ) : null}
                                            <p className="text-sm">
                                                {result.registration
                                                    .tableNumber !== null
                                                    ? t('scan.result.table', {
                                                          number: String(
                                                              result
                                                                  .registration
                                                                  .tableNumber,
                                                          ),
                                                      })
                                                    : t('scan.result.no_table')}
                                            </p>
                                        </>
                                    ) : null}

                                    {result.result === 'already_scanned' ? (
                                        <div className="space-y-2">
                                            {result.firstScannedAt ? (
                                                <p className="text-muted-foreground text-sm">
                                                    {t(
                                                        'scan.result.first_scanned_at',
                                                        {
                                                            time: formatDateTime(
                                                                result.firstScannedAt,
                                                                locale,
                                                            ),
                                                        },
                                                    )}
                                                </p>
                                            ) : null}
                                            {result.firstScannedBy ? (
                                                <p className="text-muted-foreground text-sm">
                                                    {t(
                                                        'scan.result.first_scanned_by',
                                                        {
                                                            name: result.firstScannedBy,
                                                        },
                                                    )}
                                                </p>
                                            ) : null}
                                            {canForce &&
                                            !result.forced &&
                                            !result.manual ? (
                                                <SubmitButton
                                                    type="button"
                                                    variant="destructive"
                                                    size="sm"
                                                    data-test="scan-force"
                                                    processing={pending}
                                                    onClick={() =>
                                                        lastTokenRef.current &&
                                                        submit(
                                                            lastTokenRef.current,
                                                            true,
                                                        )
                                                    }
                                                >
                                                    {t('scan.result.force')}
                                                </SubmitButton>
                                            ) : null}
                                        </div>
                                    ) : null}

                                    {result.result === 'refused' ? (
                                        result.otherEvent ? (
                                            // Vrai billet d'un autre controle du jour : l'agent
                                            // oriente l'invite, ce n'est pas une fraude.
                                            <p
                                                className="text-sm font-medium"
                                                data-test="scan-other-event"
                                            >
                                                {t('scan.result.other_event', {
                                                    name: result.otherEvent
                                                        .name,
                                                    place: [
                                                        result.otherEvent.venue,
                                                        result.otherEvent
                                                            .startsAt
                                                            ? formatDateTime(
                                                                  result
                                                                      .otherEvent
                                                                      .startsAt,
                                                                  locale,
                                                              )
                                                            : null,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(', '),
                                                })}
                                            </p>
                                        ) : (
                                            <p className="text-muted-foreground text-sm">
                                                {t('scan.result.refused_help')}
                                            </p>
                                        )
                                    ) : null}

                                    <Button
                                        variant="outline"
                                        size="sm"
                                        data-test="scan-next"
                                        onClick={() => {
                                            setResultDismissed(true);
                                            lastTokenRef.current = null;
                                        }}
                                    >
                                        {t('scan.result.next')}
                                    </Button>
                                </CardContent>
                            </Card>
                        ) : null}

                        {canAdmitWithoutScan && !event.closed ? (
                            <GuestLookupCard
                                tenantSlug={tenant.slug}
                                eventId={event.id}
                                lookup={lookup ?? null}
                                online={online}
                                blocked={scanPin === null || lock.locked}
                                canForce={canForce}
                                processing={pending}
                                onActivity={lock.registerActivity}
                                onAdmit={admitWithoutScan}
                            />
                        ) : !event.closed ? (
                            <p
                                className="text-muted-foreground text-sm"
                                data-test="guest-lookup-no-permission"
                            >
                                {t('scan.lookup.no_permission')}
                            </p>
                        ) : null}

                        {canRotateKey && event.qrPublicKey !== null ? (
                            <RotateTicketKeyCard
                                tenantSlug={tenant.slug}
                                eventId={event.id}
                                keyVersion={event.qrKeyVersion}
                            />
                        ) : null}

                        <Card data-tour="scan-recent">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t('scan.recent.title')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {recent.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">
                                        {t('scan.recent.empty')}
                                    </p>
                                ) : (
                                    <div className="divide-y">
                                        {recent.map((row) => (
                                            <div
                                                key={row.id}
                                                className="flex items-center justify-between gap-2 py-2 text-sm"
                                                data-test="scan-recent-row"
                                            >
                                                <span>
                                                    {row.name ?? '-'}
                                                    {row.manual ? (
                                                        <span className="text-muted-foreground block text-xs">
                                                            {t(
                                                                'scan.result.manual_badge',
                                                            )}
                                                        </span>
                                                    ) : null}
                                                    {row.station ? (
                                                        <span className="text-muted-foreground block text-xs">
                                                            {row.station}
                                                        </span>
                                                    ) : null}
                                                </span>
                                                <Badge
                                                    variant={
                                                        row.result ===
                                                        'accepted'
                                                            ? 'default'
                                                            : row.result ===
                                                                'refused'
                                                              ? 'destructive'
                                                              : 'secondary'
                                                    }
                                                >
                                                    {t(
                                                        `scan.results.${row.result}`,
                                                    )}
                                                </Badge>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

EventScan.layout = (props: {
    tenant: { slug: string };
    event: { id: number };
    translations: Translations;
}) => ({
    breadcrumbs: [
        {
            title: translate(props.translations, 'events.title'),
            href: eventsIndex(props.tenant.slug),
        },
        {
            title: translate(props.translations, 'scan.title'),
            href: index([props.tenant.slug, props.event.id]),
        },
    ],
});
