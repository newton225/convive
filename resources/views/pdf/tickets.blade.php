<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('guest.ticket_pdf.title', ['event' => $event['name']]) }}</title>
    {{-- Rendu hors reponse HTTP (Browsershot), donc hors CSP : voir la note sur les gabarits PDF. --}}
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { font-family: 'Instrument Sans', 'Helvetica Neue', Arial, sans-serif; color: #1b1917; }
        /* Une page par billet. Largeur fluide : c'est le format A5 du generateur qui fixe la page,
           une largeur en millimetres ici deborderait et ajouterait une page blanche. */
        .ticket { width: 100%; text-align: center; break-after: page; page-break-after: always; }
        .ticket:last-child { break-after: auto; page-break-after: auto; }
        .band { border-radius: 4mm; padding: 6mm; color: #fff; background: {{ $colors['primary'] }}; }
        .organisation { font-size: 9pt; letter-spacing: 0.08em; text-transform: uppercase; opacity: 0.85; }
        .event { font-family: 'Playfair Display', Georgia, serif; font-size: 20pt; margin: 2mm 0 1mm; }
        .when { font-size: 10pt; }
        .holder { margin-top: 7mm; }
        .name { font-size: 16pt; font-weight: 700; }
        .muted { color: #6b6862; font-size: 10pt; margin-top: 1mm; }
        .qr { width: 62mm; height: 62mm; margin-top: 6mm; image-rendering: pixelated; }
        .table { font-size: 14pt; font-weight: 700; margin-top: 4mm; }
        .notice { margin-top: 6mm; border-top: 0.4mm solid {{ $colors['secondary'] }}; padding-top: 4mm; font-size: 9pt; color: #6b6862; }
        .position { margin-top: 2mm; font-size: 8pt; color: #9a968f; }
    </style>
</head>
<body>
    @foreach ($tickets as $index => $ticket)
        <section class="ticket">
            <div class="band">
                @if ($organisation)
                    <div class="organisation">{{ $organisation }}</div>
                @endif
                <div class="event">{{ $event['name'] }}</div>
                @if ($event['startsAt'])
                    <div class="when">
                        {{ $event['startsAt']->locale(app()->getLocale())->translatedFormat('j F Y, H:i') }}
                        @if ($event['venue'])
                            · {{ $event['venue'] }}
                        @endif
                    </div>
                @endif
            </div>

            <div class="holder">
                <div class="name">{{ $ticket['name'] }}</div>
                <div class="muted">{{ $ticket['unit'] }}</div>
                @if ($ticket['guestOf'])
                    <div class="muted">{{ __('guest.ticket_pdf.guest_of', ['name' => $ticket['guestOf']]) }}</div>
                @endif
            </div>

            <img class="qr" src="{{ $ticket['qrImage'] }}" alt="{{ __('guest.ticket.title') }}">

            <div class="table">
                @if ($ticket['tableNumber'] !== null)
                    {{ __('guest.ticket.table', ['number' => $ticket['tableNumber']]) }}
                @else
                    {{ __('guest.ticket.no_table') }}
                @endif
            </div>

            <div class="notice">
                {{ __('guest.ticket_pdf.notice') }}
                @if (count($tickets) > 1)
                    <div class="position">{{ __('guest.ticket_pdf.position', ['current' => $index + 1, 'total' => count($tickets)]) }}</div>
                @endif
            </div>
        </section>
    @endforeach
</body>
</html>
