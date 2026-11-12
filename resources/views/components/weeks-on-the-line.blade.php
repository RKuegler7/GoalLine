{{-- Weeks on the line, in the Clubhouse hero: finished GoalWeeks in a row at In line or better, with a tap-to-open
     explanation. At zero it's an invitation, not a bare 0. --}}
@props(['weeks', 'best' => null])

<details {{ $attributes->class('group relative') }} data-weeks-on-the-line>
    <summary class="inline-flex min-h-9 cursor-pointer list-none items-center gap-2 rounded-full bg-ivory/10 px-3.5 text-sm font-semibold text-ivory ring-1 ring-ivory/20 ring-inset transition hover:bg-ivory/15 [&::-webkit-details-marker]:hidden">
        <x-icon name="line" class="size-4 text-grass" />
        @if ($weeks === 0)
            Start your line this week
        @else
            <span><span class="tabular-nums">{{ $weeks }}</span> {{ Str::plural('week', $weeks) }} on the line</span>
        @endif
        <x-icon name="info" class="size-3.5 text-ivory/70" />
        <span class="sr-only">: what this means</span>
    </summary>
    <div class="absolute left-0 z-20 mt-2 w-72 max-w-[calc(100vw-2rem)] rounded-xl bg-white px-3 py-2.5 text-[13px] leading-snug text-ink shadow-lg ring-1 ring-line" role="note">
        <p class="font-semibold text-club">Weeks on the line</p>
        <p class="mt-1 text-ink/80">GoalWeeks in a row that finished In line or better: {{ (int) round(App\GoalWeekResult::IN_LINE_AT * 100) }}% of your plan or more. This week counts once it's final.</p>
        @if ($best !== null)
            <p class="mt-1 text-ink/70">Your best run: {{ $best }} {{ Str::plural('week', $best) }}.</p>
        @endif
    </div>
</details>
