{{-- A slim progress bar: 0 to 100 (more is shown full). Grass when on track, gold past the line. --}}
@props(['percent', 'label' => null, 'tone' => 'grass'])

@php $width = max(0, min(100, (int) round($percent))); @endphp
<div {{ $attributes->class('h-1.5 overflow-hidden rounded-full bg-ink/10') }} role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $width }}" @if ($label) aria-label="{{ $label }}" @endif>
    <div @class(['h-full rounded-full', 'bg-grass' => $tone === 'grass', 'bg-gold' => $tone === 'gold', 'bg-club' => $tone === 'club', 'bg-red-400' => $tone === 'short']) style="width: {{ $width }}%"></div>
</div>
