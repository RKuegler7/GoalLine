{{-- A segmented control: two to four options in one pill. Each option is a link (aria-current on the chosen one), or
     a button with data-segment for in-page panels (app.js switches [data-segment-panel] in the same [data-segmented]).
     $options: [['label' => 'GoalLeagues', 'href' => …, 'active' => true, 'count' => 2] …] or with 'segment' => 'id'. --}}
@props(['options', 'label' => 'View'])

<div {{ $attributes->class('flex rounded-full bg-ink/5 p-0.5') }} role="tablist" aria-label="{{ $label }}">
    @foreach ($options as $option)
        @php $active = $option['active'] ?? false; @endphp
        @if (isset($option['href']))
            <a href="{{ $option['href'] }}" role="tab" aria-selected="{{ $active ? 'true' : 'false' }}" @if ($active) aria-current="page" @endif
        @else
            <button type="button" role="tab" data-segment="{{ $option['segment'] }}" aria-selected="{{ $active ? 'true' : 'false' }}"
        @endif
            @class([
                'flex min-h-9 flex-1 items-center justify-center gap-1.5 rounded-full px-3 text-[13px] font-semibold transition',
                'bg-white text-club shadow-sm' => $active,
                'text-ink/70 hover:text-ink' => ! $active,
            ])>
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
