@php
    // Le meme talon que `resources/js/components/ticket-template/branded-ticket.tsx`, sur les memes
    // donnees (`App\Support\TicketCard`) : les deux evoluent ensemble. Festons et encoches sont des
    // disques couleur du fond de page poses sur le bord, comme dans le composant.
    $brand = $design['brand'];
    $elements = $design['elements'];
    $model = $design['model'];
    $align = $model === 'sober' ? 'left' : 'center';
    $backdrop = '#ebe8e4';
    $scallops = 13;
    // Meme seuil que `CompactCompanionsAbove` dans le composant.
    $compactCompanionsAbove = 4;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('guest.ticket_pdf.title', ['event' => $event['name']]) }}</title>
    {{-- Rendu hors reponse HTTP, donc hors CSP : voir la note sur les gabarits PDF. --}}
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: {{ $backdrop }}; }
        body { font-family: 'Instrument Sans', 'Helvetica Neue', Arial, sans-serif; color: #1b1917; }
        /* Une page A5 par billet ; le talon y est centre sur le fond gris. */
        /* Pas de hauteur fixe : le moteur PDF ignore box-sizing, une hauteur plus la marge du haut
           deborderait sur une seconde page. */
        .page { padding-top: 11mm; page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .talon { position: relative; width: 90mm; height: 187mm; margin: 0 auto; background: #fff; }
        .scallop { position: absolute; width: 5mm; height: 5mm; border-radius: 2.5mm; background: {{ $backdrop }}; }
        .notch { position: absolute; width: 7mm; height: 7mm; border-radius: 3.5mm; background: {{ $backdrop }}; }
        /* Hauteur fixe aux proportions du fond recadre (1000 x 850), comme le haut du talon a
           l'ecran : l'image le couvre exactement, sans deformation. */
        .stub { position: relative; height: 76.5mm; padding: 9mm 6mm 0; text-align: center; overflow: hidden; }
        .stub-background { position: absolute; top: 0; left: 0; width: 90mm; height: 76.5mm; }
        .stub-content { position: relative; }
        .stub-table.on-image { display: inline-block; padding: 0.6mm 3mm; border-radius: 3mm; background: #fff; }
        .stub.classic { background: {{ $design['tints']['stub'] }}; }
        .qr-frame { display: inline-block; padding: 1.5mm; background: #fff; border: 0.3mm solid #e2dfda; border-radius: 1.5mm; }
        /* Avec la marge et la bordure du cadre : 52,9 mm sur 90, la part du talon que le cadre du QR
           occupe a l'ecran (188 px sur 320) et dans le repere de rognage. */
        .qr { width: 49.3mm; height: 49.3mm; }
        .stub-table { margin-top: 2.5mm; font-size: 10pt; font-weight: 700; }
        .perforation { position: relative; height: 0; }
        .perforation-line { margin: 0 5mm; border-top: 0.6mm dotted #a8a49d; }
        /* Le fond du bas du talon, estompe comme a l'ecran (15 %), aux proportions de l'image
           recadree (1000 x 1228). Le texte, positionne, se peint par-dessus. */
        .body-background { position: absolute; top: 76.5mm; left: 0; width: 90mm; height: 110.5mm; opacity: 0.15; }
        .body { position: relative; padding: 5mm 7mm 0; text-align: {{ $align }}; }
        .organisation { font-size: 7.5pt; letter-spacing: 0.08em; text-transform: uppercase; color: #6b6862; }
        .logo { height: 7mm; vertical-align: middle; margin-right: 2mm; }
        .event { margin-top: 3mm; font-size: 15pt; font-weight: 700; line-height: 1.2; color: {{ $brand['colors']['primary'] }}; }
        .event.elegant { font-family: 'Playfair Display', Georgia, serif; font-size: 16pt; font-weight: 400; }
        .rule { width: 12mm; height: 0.6mm; margin: 1.5mm {{ $align === 'center' ? 'auto' : '0' }} 0; background: {{ $brand['colors']['secondary'] }}; }
        .when { margin-top: 2mm; font-size: 8pt; color: #6b6862; }
        .label { font-size: 7.5pt; color: #8a867f; }
        .field { margin-top: 3mm; }
        .holder { font-size: 12pt; font-weight: 700; }
        .unit { font-size: 8pt; color: #6b6862; }
        .pair { width: {{ $align === 'center' ? '100%' : '45mm' }}; margin-top: 3mm; border-collapse: collapse; }
        .pair td { width: 50%; padding: 0; text-align: {{ $align }}; vertical-align: top; font-size: 10pt; font-weight: 600; }
        .host { margin-top: 3mm; padding: 2mm 3mm; border-radius: 1.5mm; background: {{ $design['tints']['host'] }}; }
        .host-name { font-size: 10pt; font-weight: 600; }
        .companion { font-size: 9pt; line-height: 1.45; }
        .companion span { font-size: 7.5pt; color: #8a867f; }
        /* Au-dela de quelques noms : deux colonnes sans les unites, pour que dix accompagnateurs
           tiennent au-dessus du pied, la hauteur du talon etant fixe. */
        .companions { width: 100%; border-collapse: collapse; }
        .companions td { width: 50%; padding: 0 1mm; text-align: {{ $align }}; vertical-align: top; font-size: 7pt; line-height: 1.2; }
        /* Validite, cachet et signature sur une meme ligne : la place va aux noms. */
        .footer { position: absolute; left: 0; right: 0; bottom: 0; padding: 3mm 7mm 5mm; text-align: {{ $align }}; }
        .valid { display: inline-block; vertical-align: middle; padding: 1mm 3.5mm; border-radius: 3mm; font-size: 8pt; font-weight: 600; color: {{ $brand['colors']['primary'] }}; background: {{ $design['tints']['badge'] }}; }
        /* La mention de l'editeur, dans la marge basse du talon : elle n'ajoute aucune hauteur.
           Placee depuis le haut du talon : le moteur PDF situe mal un `bottom` imbrique. */
        .copyright { position: absolute; left: 7mm; top: 182mm; width: 76mm; font-size: 5.5pt; line-height: 1; color: #8a867f; text-align: {{ $align }}; }
        .marks { display: inline-block; margin-left: 3mm; vertical-align: middle; }
        .stamp { height: 11mm; margin-right: 2mm; vertical-align: middle; }
        .signature { height: 9mm; max-width: 20mm; vertical-align: middle; }
    </style>
</head>
<body>
    @foreach ($tickets as $ticket)
        <div class="page">
            <div class="talon">
                <div class="stub {{ $model === 'classic' && ! $brand['backgroundUrl'] ? 'classic' : '' }}">
                    @if ($brand['backgroundUrl'])
                        <img class="stub-background" src="{{ $brand['backgroundUrl'] }}" alt="">
                    @endif
                    <div class="stub-content">
                    <span class="qr-frame"><img class="qr" src="{{ $ticket['qrImage'] }}" alt="{{ __('ticket_template.preview.qr_of', ['name' => $ticket['holder']['name']]) }}"></span>
                    <div class="stub-table {{ $brand['backgroundUrl'] ? 'on-image' : '' }}">
                        @if ($ticket['tableNumber'] !== null)
                            {{ __('guest.ticket.table', ['number' => $ticket['tableNumber']]) }}
                        @elseif ($ticket['seatsAtTables'])
                            {{ __('guest.ticket.no_table') }}
                        @else
                            {{ $ticket['priceCategory'] }}
                        @endif
                    </div>
                    </div>
                </div>

                @if ($brand['bodyBackgroundUrl'])
                    <img class="body-background" src="{{ $brand['bodyBackgroundUrl'] }}" alt="">
                @endif

                <div class="perforation">
                    <span class="notch" style="top: -3.5mm; left: -3.5mm;"></span>
                    <span class="notch" style="top: -3.5mm; right: -3.5mm;"></span>
                    <div class="perforation-line"></div>
                </div>

                <div class="body">
                    <div class="organisation">
                        @if ($brand['logoUrl'])
                            <img class="logo" src="{{ $brand['logoUrl'] }}" alt="">
                        @endif
                        {{ $brand['displayName'] }}
                    </div>
                    <div class="event {{ $model === 'elegant' ? 'elegant' : '' }}">{{ $event['name'] }}</div>
                    @if ($model === 'elegant')
                        <div class="rule"></div>
                    @endif
                    @if ($event['startsAt'] || $event['venue'])
                        <div class="when">
                            {{ collect([
                                $event['startsAt']?->locale(app()->getLocale())->translatedFormat('j F Y, H:i'),
                                $event['venue'],
                            ])->filter()->implode(' · ') }}
                        </div>
                    @endif

                    <div class="field">
                        <div class="label">{{ __('ticket_template.preview.guest') }}</div>
                        <div class="holder">{{ $ticket['holder']['name'] }}</div>
                        <div class="unit">{{ $ticket['holder']['unit'] }}</div>
                    </div>

                    @if ($ticket['priceCategory'])
                        <div class="field">
                            <div class="label">{{ __('ticket_template.preview.price_category') }}</div>
                            <div class="holder">{{ $ticket['priceCategory'] }}</div>
                        </div>
                    @endif

                    <table class="pair">
                        <tr>
                            @if ($ticket['seatsAtTables'])
                                <td><div class="label">{{ __('ticket_template.preview.table') }}</div>{{ $ticket['tableNumber'] ?? '-' }}</td>
                            @endif
                            <td><div class="label">{{ __('ticket_template.preview.seats') }}</div>{{ $ticket['seats'] }}</td>
                        </tr>
                    </table>

                    @if ($ticket['host'])
                        <div class="host">
                            <div class="label">{{ __('ticket_template.preview.host') }}</div>
                            <div class="host-name">{{ $ticket['host']['name'] }}</div>
                            <div class="unit">{{ collect([$ticket['host']['unit'], $ticket['host']['reference']])->filter()->implode(' · ') }}</div>
                        </div>
                    @endif

                    @if (count($ticket['companions']) > 0)
                        <div class="field">
                            <div class="label">{{ __('ticket_template.preview.companions') }}</div>
                            @if (count($ticket['companions']) > $compactCompanionsAbove)
                                <table class="companions">
                                    @foreach (array_chunk($ticket['companions'], 2) as $row)
                                        <tr>
                                            <td>{{ $row[0]['name'] }}</td>
                                            <td>{{ $row[1]['name'] ?? '' }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            @else
                                @foreach ($ticket['companions'] as $companion)
                                    <div class="companion">{{ $companion['name'] }} <span>· {{ $companion['unit'] }}@if ($companion['priceCategory']) · {{ $companion['priceCategory'] }}@endif</span></div>
                                @endforeach
                            @endif
                        </div>
                    @endif
                </div>

                <div class="footer">
                    <span class="notch" style="top: -3.5mm; left: -3.5mm;"></span>
                    <span class="notch" style="top: -3.5mm; right: -3.5mm;"></span>
                    <span class="valid">{{ __('ticket_template.preview.valid') }}</span>
                    @if ($brand['stampUrl'] || $brand['signatureUrl'])
                        <div class="marks">
                            @if ($brand['stampUrl'])
                                <img class="stamp" src="{{ $brand['stampUrl'] }}" alt="">
                            @endif
                            @if ($brand['signatureUrl'])
                                <img class="signature" src="{{ $brand['signatureUrl'] }}" alt="">
                            @endif
                        </div>
                    @endif
                </div>

                <div class="copyright">{{ __('ticket_template.preview.copyright', ['year' => now()->year]) }}</div>

                {{-- En dernier : peints par-dessus le haut du billet et son fond eventuel. --}}
                @for ($i = 0; $i < $scallops; $i++)
                    <span class="scallop" style="top: -2.5mm; left: {{ 0.6 + $i * 7 }}mm;"></span>
                    <span class="scallop" style="bottom: -2.5mm; left: {{ 0.6 + $i * 7 }}mm;"></span>
                @endfor
            </div>
        </div>
    @endforeach
</body>
</html>
