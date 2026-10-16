<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('treasury.pdf.title') }}</title>
    @include('pdf.partials.styles')
</head>
<body>
    @include('pdf.partials.watermark')
    @include('pdf.partials.letterhead')

    <h1>{{ __('treasury.pdf.title') }}</h1>
    <div class="muted">
        @if ($from || $to)
            {{ __('treasury.pdf.period', [
                'from' => $from?->locale(app()->getLocale())->translatedFormat('j F Y') ?? '...',
                'to' => $to?->locale(app()->getLocale())->translatedFormat('j F Y') ?? '...',
            ]) }}
        @else
            {{ __('treasury.pdf.whole_period') }}
        @endif
    </div>

    <table class="cards" style="margin-top: 12px;">
        <tr>
            <td>
                <div class="muted">{{ __('treasury.totals.collected') }}</div>
                <div class="value">{{ \App\Support\Money::format($overview['totals']['collected']) }}</div>
                <div class="muted">{{ __('treasury.totals.collected_detail') }}</div>
            </td>
            <td>
                <div class="muted">{{ __('treasury.totals.refunded') }}</div>
                <div class="value">{{ \App\Support\Money::format($overview['totals']['refunded']) }}</div>
                <div class="muted">{{ trans_choice('treasury.totals.fees', $overview['totals']['fees'], ['amount' => \App\Support\Money::format($overview['totals']['fees'])]) }}</div>
            </td>
            <td>
                <div class="muted">{{ __('treasury.totals.due') }}</div>
                <div class="value">{{ \App\Support\Money::format($overview['totals']['due']) }}</div>
            </td>
            <td>
                <div class="muted">{{ __('treasury.totals.net') }}</div>
                <div class="value">{{ \App\Support\Money::format($overview['totals']['net']) }}</div>
                <div class="muted">{{ __('treasury.totals.net_detail') }}</div>
            </td>
        </tr>
    </table>

    <h2>{{ __('treasury.events.title') }}</h2>

    @if (count($overview['events']) === 0)
        <p class="muted">{{ __('treasury.events.empty') }}</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>{{ __('treasury.events.name') }}</th>
                    <th>{{ __('treasury.events.date') }}</th>
                    <th class="right">{{ __('treasury.events.collected') }}</th>
                    <th class="right">{{ __('treasury.events.refunded') }}</th>
                    <th class="right">{{ __('treasury.events.fees') }}</th>
                    <th class="right">{{ __('treasury.events.due') }}</th>
                    <th class="right">{{ __('treasury.events.net') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($overview['events'] as $event)
                    <tr>
                        <td>{{ $event['name'] }}</td>
                        <td>{{ $event['startsAt'] ? \Illuminate\Support\Carbon::parse($event['startsAt'])->locale(app()->getLocale())->translatedFormat('j F Y') : '-' }}</td>
                        <td class="right">{{ \App\Support\Money::format($event['collected']) }}</td>
                        <td class="right">{{ \App\Support\Money::format($event['refunded']) }}</td>
                        <td class="right">{{ \App\Support\Money::format($event['fees']) }}</td>
                        <td class="right">{{ \App\Support\Money::format($event['due']) }}</td>
                        <td class="right">{{ \App\Support\Money::format($event['net']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>{{ __('treasury.accounts.title') }}</h2>
    <p class="muted">{{ __('treasury.accounts.description') }}</p>

    @if (count($overview['accounts']) === 0)
        <p class="muted">{{ __('treasury.accounts.empty') }}</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>{{ __('treasury.accounts.label') }}</th>
                    <th>{{ __('treasury.accounts.channel') }}</th>
                    <th class="right">{{ __('treasury.accounts.collected') }}</th>
                    <th class="right">{{ __('treasury.accounts.refunded') }}</th>
                    <th class="right">{{ __('treasury.accounts.due') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($overview['accounts'] as $account)
                    <tr>
                        <td>{{ $account['label'] }}</td>
                        <td>{{ $account['channelLabel'] ?? '-' }}</td>
                        <td class="right">{{ \App\Support\Money::format($account['collected']) }}</td>
                        <td class="right">{{ \App\Support\Money::format($account['refunded']) }}</td>
                        <td class="right">{{ \App\Support\Money::format($account['due']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>{{ __('treasury.refunds.title') }}</h2>
    <p class="muted">{{ trans_choice('treasury.refunds.count', $overview['refundsDueCount'], ['count' => $overview['refundsDueCount']]) }}</p>

    @if (count($overview['refundsDue']) > 0)
        <table>
            <thead>
                <tr>
                    <th>{{ __('treasury.refunds.name') }}</th>
                    <th>{{ __('treasury.refunds.reference') }}</th>
                    <th>{{ __('treasury.refunds.event') }}</th>
                    <th class="right">{{ __('treasury.refunds.amount') }}</th>
                    <th>{{ __('treasury.refunds.cancelled_on') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($overview['refundsDue'] as $refund)
                    <tr>
                        <td>{{ $refund['name'] }}</td>
                        <td>{{ $refund['reference'] ?? '-' }}</td>
                        <td>{{ $refund['eventName'] }}</td>
                        <td class="right">{{ \App\Support\Money::format($refund['amount']) }}</td>
                        <td>{{ $refund['cancelledAt'] ? \Illuminate\Support\Carbon::parse($refund['cancelledAt'])->locale(app()->getLocale())->translatedFormat('j F Y') : '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if ($overview['refundsDueCount'] > count($overview['refundsDue']))
            <p class="muted">{{ __('treasury.refunds.more', ['limit' => $refundListLimit]) }}</p>
        @endif
    @endif

    @include('pdf.partials.signature')
</body>
</html>
