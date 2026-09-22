<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('reports.pdf.title') }} - {{ $event['name'] }}</title>
    @include('pdf.partials.styles')
</head>
<body>
    @include('pdf.partials.letterhead')

    <h1>{{ __('reports.pdf.title') }}</h1>
    <div class="muted">
        {{ $event['name'] }}
        @if ($event['startsAt'])
            , {{ $event['startsAt']->locale(app()->getLocale())->translatedFormat('j F Y') }}
        @endif
    </div>

    <table class="cards" style="margin-top: 12px;">
        <tr>
            <td>
                <div class="muted">{{ __('reports.cards.confirmed') }}</div>
                <div class="value">{{ $report['confirmedRegistrations'] }}</div>
                <div class="muted">{{ trans_choice('reports.cards.seats', $report['confirmedSeats'], ['count' => $report['confirmedSeats']]) }}</div>
            </td>
            <td>
                <div class="muted">{{ __('reports.cards.present') }}</div>
                <div class="value">{{ $report['presentRegistrations'] }}</div>
                <div class="muted">{{ trans_choice('reports.cards.seats', $report['presentSeats'], ['count' => $report['presentSeats']]) }}</div>
            </td>
            <td>
                <div class="muted">{{ __('reports.cards.absent') }}</div>
                <div class="value">{{ $report['absentRegistrations'] }}</div>
                <div class="muted">{{ trans_choice('reports.cards.seats', $report['absentSeats'], ['count' => $report['absentSeats']]) }}</div>
            </td>
            <td>
                <div class="muted">{{ __('reports.cards.collected') }}</div>
                <div class="value">{{ \App\Support\Money::format($report['collectedAmount']) }}</div>
            </td>
        </tr>
    </table>

    <p>
        {{ __('reports.cards.average_scan_interval') }} :
        @if ($report['averageScanIntervalSeconds'] === null)
            {{ __('reports.cards.not_available') }}
        @else
            {{ trans_choice('reports.cards.seconds', $report['averageScanIntervalSeconds'], ['count' => $report['averageScanIntervalSeconds']]) }}
        @endif
    </p>

    <h2>{{ __('reports.units.title') }}</h2>

    @if (count($report['units']) === 0)
        <p class="muted">{{ __('reports.units.empty') }}</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>{{ __('reports.units.unit') }}</th>
                    <th class="right">{{ __('reports.units.confirmed') }}</th>
                    <th class="right">{{ __('reports.units.present') }}</th>
                    <th class="right">{{ __('reports.units.collected') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['units'] as $unit)
                    <tr>
                        <td>{{ $unit['unit'] }}</td>
                        <td class="right">{{ $unit['confirmedRegistrations'] }}</td>
                        <td class="right">{{ $unit['presentRegistrations'] }}</td>
                        <td class="right">{{ \App\Support\Money::format($unit['collectedAmount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="muted" style="margin-top: 10px;">{{ __('reports.units.note') }}</p>

    @include('pdf.partials.signature')
</body>
</html>
