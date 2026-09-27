<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('registrations.pdf.title') }} - {{ $event['name'] }}</title>
    @include('pdf.partials.styles')
</head>
<body>
    @include('pdf.partials.watermark')
    @include('pdf.partials.letterhead')

    <h1>{{ __('registrations.pdf.title') }}</h1>
    <div class="muted">
        {{ $event['name'] }}
        @if ($event['startsAt'])
            , {{ $event['startsAt']->locale(app()->getLocale())->translatedFormat('j F Y') }}
        @endif
    </div>

    @if (count($registrations) === 0)
        <p class="muted">{{ __('registrations.pdf.empty') }}</p>
    @else
        <table style="margin-top: 12px;">
            <thead>
                <tr>
                    <th>{{ __('registrations.columns.name') }}</th>
                    <th>{{ __('registrations.columns.export.phone') }}</th>
                    <th>{{ __('registrations.columns.unit') }}</th>
                    <th class="right">{{ __('registrations.columns.party_size') }}</th>
                    <th class="right">{{ __('registrations.columns.amount_due') }}</th>
                    <th>{{ __('registrations.columns.status') }}</th>
                    <th>{{ __('registrations.columns.table') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($registrations as $registration)
                    <tr>
                        <td>{{ $registration['name'] }}</td>
                        <td>{{ $registration['phone'] }}</td>
                        <td>{{ $registration['unit'] }}</td>
                        <td class="right">{{ $registration['partySize'] }}</td>
                        <td class="right">{{ \App\Support\Money::format($registration['amountDue']) }}</td>
                        <td>{{ $registration['statusLabel'] }}</td>
                        <td>{{ $registration['tableNumber'] ?? __('registrations.actions.no_table') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="muted">
            {{ trans_choice('registrations.pdf.total', count($registrations), ['count' => count($registrations)]) }}
        </p>
    @endif

    @include('pdf.partials.signature')
</body>
</html>
