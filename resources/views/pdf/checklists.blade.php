<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('registrations.pdf.checklists_title') }} - {{ $event['name'] }}</title>
    @include('pdf.partials.styles')
</head>
<body>
    @include('pdf.partials.letterhead')

    <h1>{{ __('registrations.pdf.checklists_title') }}</h1>
    <div class="muted">
        {{ $event['name'] }}
        @if ($event['startsAt'])
            , {{ $event['startsAt']->locale(app()->getLocale())->translatedFormat('j F Y') }}
        @endif
    </div>

    @if (count($tables) === 0)
        <p class="muted">{{ __('registrations.pdf.checklists_empty') }}</p>
    @endif

    @foreach ($tables as $table)
        <div class="table-block">
            <h2>{{ __('registrations.pdf.table_heading', ['number' => $table['number'], 'seats' => $table['seats'], 'capacity' => $table['capacity']]) }}</h2>

            <table>
                <thead>
                    <tr>
                        <th style="width: 24px;">{{ __('registrations.pdf.present') }}</th>
                        <th>{{ __('registrations.columns.name') }}</th>
                        <th>{{ __('registrations.columns.unit') }}</th>
                        <th class="right">{{ __('registrations.columns.party_size') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($table['registrations'] as $registration)
                        <tr>
                            <td><span class="box"></span></td>
                            <td>
                                {{ $registration['name'] }}
                                @if (count($registration['companions']) > 0)
                                    <div class="muted">+ {{ implode(', ', $registration['companions']) }}</div>
                                @endif
                            </td>
                            <td>{{ $registration['unit'] }}</td>
                            <td class="right">{{ $registration['partySize'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    @include('pdf.partials.signature')
</body>
</html>
