@if ($letterhead['stamp'] || $letterhead['signature'] || $letterhead['representative'])
    <div class="signature">
        @if ($letterhead['representative'])
            <div>{{ __('reports.pdf.signed_by', ['name' => $letterhead['representative']]) }}</div>
        @endif
        @if ($letterhead['signature'])
            <img src="{{ $letterhead['signature'] }}" alt="">
        @endif
        @if ($letterhead['stamp'])
            <img src="{{ $letterhead['stamp'] }}" alt="">
        @endif
    </div>
@endif

<div class="footer">
    {{ __('reports.pdf.generated_at', ['date' => $generatedAt->locale(app()->getLocale())->translatedFormat('j F Y, H:i')]) }}
</div>
