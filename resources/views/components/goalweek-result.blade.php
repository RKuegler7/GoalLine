{{-- A GoalWeek result pill with its line points, or a neutral "Not started" on day one. Short is red, In line green,
     Past the line a golden shine, Smashed a blue diamond. A Smashed week waiting on proof says so. --}}
@props(['result', 'closeCall' => false, 'notStarted' => false, 'needsProof' => false])

@if ($notStarted)
    <span {{ $attributes->class('eyebrow inline-flex items-center rounded-full bg-ivory px-2.5 py-1 text-[0.65rem] text-ink/55 ring-1 ring-inset ring-line') }}>
        Not started
    </span>
@else
    <span {{ $attributes->class('inline-flex flex-wrap items-center gap-1.5') }}>
        <span @class([
            'eyebrow inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[0.65rem]',
            'bg-red-50 text-red-700' => $result === App\GoalWeekResult::Short,
            'bg-amber-100 text-amber-800' => $result === App\GoalWeekResult::InLine && $closeCall,
            'bg-grass text-white' => $result === App\GoalWeekResult::InLine && ! $closeCall,
            'bg-gradient-to-r from-gold via-[#f0dca4] to-gold text-ink shadow-sm' => $result === App\GoalWeekResult::Past,
            'bg-sky-600 text-white shadow-sm' => $result === App\GoalWeekResult::Smashed,
        ])>
            @if ($result === App\GoalWeekResult::Smashed)
                <svg viewBox="0 0 12 12" class="size-2.5" fill="currentColor" aria-hidden="true"><path d="M6 0.5 11.5 6 6 11.5 0.5 6z" /></svg>
            @endif
            {{ $result->label() }}{{ $closeCall ? ', close call' : '' }}
            <span class="opacity-70">&middot; {{ $result->points() }} {{ Str::plural('pt', $result->points()) }}</span>
        </span>
        @if ($needsProof)
            <span class="rounded-full bg-sky-50 px-2 py-0.5 text-[0.65rem] font-semibold text-sky-800 ring-1 ring-inset ring-sky-200">Needs proof to count as Smashed</span>
        @endif
    </span>
@endif
