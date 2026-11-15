{{-- A segmented control: two to four options in one pill. Each option is a link (aria-current on the chosen one), or
     a button with data-segment for in-page panels (app.js switches [data-segment-panel] in the same [data-segmented]).
     $options: [['label' => 'GoalLeagues', 'href' => …, 'active' => true, 'count' => 2, 'tone' => 'league'] …] or with
     'segment' => 'id'. A tone matches the start buttons: 'league' (Grass Green) or 'challenge' (Championship Gold).
     The chosen option is filled with its colour; the others show it in the label and a small dot, so the colours are
     always visible. Toned styles follow aria-selected, so they stay right when app.js switches panels. --}}
@props(['options', 'label' => 'View'])

@php
    $tones = [
        'league' => ['group text-club aria-selected:bg-grass aria-selected:text-ink aria-selected:shadow-sm', 'bg-grass'],
        'challenge' => ['group text-gold-ink aria-selected:bg-gold aria-selected:text-ink aria-selected:shadow-sm', 'bg-gold'],
    ];
@endphp

<div {{ $attributes->class('flex rounded-full bg-ink/5 p-0.5') }} role="tablist" aria-label="{{ $label }}">
    @foreach ($options as $option)
        @php
            $active = $option['active'] ?? false;
            $tone = $tones[$option['tone'] ?? ''] ?? null;
            $look = $tone
                ? $tone[0]
                : ($active ? 'bg-white text-club shadow-sm' : 'text-ink/70 hover:text-ink');
        @endphp
        @if (isset($option['href']))
            <a href="{{ $option['href'] }}" role="tab" aria-selected="{{ $active ? 'true' : 'false' }}" @if ($active) aria-current="page" @endif
        @else
            <button type="button" role="tab" data-segment="{{ $option['segment'] }}" aria-selected="{{ $active ? 'true' : 'false' }}" @if ($tone) data-tone="{{ $option['tone'] }}" @endif
        @endif
            class="flex min-h-9 flex-1 items-center justify-center gap-1.5 rounded-full px-3 text-[13px] font-semibold transition {{ $look }}">
            @if ($tone)
                <span class="size-2 shrink-0 rounded-full {{ $tone[1] }} group-aria-selected:hidden" aria-hidden="true"></span>
            @endif
            {{ $option['label'] }}
            @if (($option['count'] ?? null) !== null)
                <span class="rounded-full bg-ink/10 px-1.5 text-[11px] tabular-nums">{{ $option['count'] }}</span>
            @endif
        @if (isset($option['href']))
            </a>
        @else
            </button>
        @endif
    @endforeach
</div>
