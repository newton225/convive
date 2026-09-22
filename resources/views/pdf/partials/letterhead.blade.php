<div class="letterhead">
    <div class="name">{{ $letterhead['name'] }}</div>
    @if ($letterhead['legalLine'])
        <div class="muted">{{ $letterhead['legalLine'] }}</div>
    @endif
    @if ($letterhead['contactLine'])
        <div class="muted">{{ $letterhead['contactLine'] }}</div>
    @endif
</div>
