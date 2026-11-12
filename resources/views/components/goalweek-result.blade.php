{{-- A GoalWeek result pill with its line stars, or a neutral "Not started" on day one. Short is red, In line green,
     Past the line a golden shine, Smashed a blue diamond. A Smashed week waiting on proof says so. --}}
@props(['result', 'closeCall' => false, 'notStarted' => false, 'needsProof' => false, 'compact' => false])

@if ($notStarted)
    <span {{ $attributes->class(['eyebrow inline-flex items-center rounded-full bg-ivory text-[11px] whitespace-nowrap text-ink/70 ring-1 ring-inset ring-line', 'px-2.5 py-1' => ! $compact, 'px-1.5 py-0.5 tracking-[0.04em]' => $compact]) }}>
        Not started
    </span>
@else
    <span {{ $attributes->class('inline-flex flex-wrap items-center gap-1.5') }}>
        <span @class([
            'eyebrow inline-flex items-center gap-1.5 rounded-full text-[11px] whitespace-nowrap',
            'px-2.5 py-1' => ! $compact,
            'px-1.5 py-0.5 tracking-[0.04em]' => $compact,
            'bg-red-50 text-red-700' => $result === App\GoalWeekResult::Short,
            'bg-amber-100 text-amber-800' => $result === App\GoalWeekResult::InLine && $closeCall,
            'bg-grass text-ink' => $result === App\GoalWeekResult::InLine && ! $closeCall,
            'bg-gradient-to-r from-gold via-[#f0dca4] to-gold text-ink shadow-sm' => $result === App\GoalWeekResult::Past,
            'bg-sky-700 text-white shadow-sm' => $result === App\GoalWeekResult::Smashed,
        ])>
            @if ($result === App\GoalWeekResult::Smashed)
                <svg viewBox="0 0 12 12" class="size-2.5" fill="currentColor" aria-hidden="true"><path d="M6 0.5 11.5 6 6 11.5 0.5 6z" /></svg>
            @endif
            @if ($compact)
                {{-- Tight spaces show the short name; the full one is there for screen readers (and the colour says it). --}}
                <span aria-hidden="true">{{ match ($result) { App\GoalWeekResult::Short => 'Short', App\GoalWeekResult::InLine => 'In line', App\GoalWeekResult::Past => 'Past', App\GoalWeekResult::Smashed => 'Smashed' } }}</span>
                <span class="sr-only">{{ $result->label() }}{{ $closeCall ? ', close call' : '' }}</span>
            @else
                {{ $result->label() }}{{ $closeCall ? ', close call' : '' }}
            @endif
            <span @class(["sr-only" => $compact])>&middot; {{ $result->points() }} {{ Str::plural("star", $result->points()) }}</span>
        </span>
        @if ($needsProof)
            <span class="rounded-full bg-sky-50 px-2 py-0.5 text-[11px] font-semibold text-sky-800 ring-1 ring-inset ring-sky-200">Needs proof to count as Smashed</span>
        @endif
    </span>
@endif
