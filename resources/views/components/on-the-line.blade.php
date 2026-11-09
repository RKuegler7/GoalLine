{{-- Days on the line this week: a calm row of seven days, filled for each day with a check-in. No red, ever. --}}
@props(['streak'])

@php
    $days = $streak->thisWeek();
    $count = $streak->daysOnTheLineThisWeek();
@endphp

<section data-region="on-the-line" {{ $attributes->class('rounded-2xl border border-line bg-white px-5 py-4') }}>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="font-serif text-lg font-bold text-club tracking-tight">{{ $count }} {{ Str::plural('day', $count) }} on the line this week</p>
            <p class="text-sm text-ink/70">
                {{ $streak->checkedInToday() ? 'Today is on the line. Trust the process.' : 'One more day on the line: one check-in today does it.' }}
            </p>
        </div>
        <ol class="flex items-center gap-1.5" aria-label="This week, Monday to Sunday">
            @foreach ($days as $date => $active)
                @php
                    $day = Carbon\Carbon::parse($date);
                @endphp
                <li class="flex flex-col items-center gap-1">
                    <span @class([
                        'grid size-7 place-items-center rounded-full text-[0.6rem] font-semibold',
                        'bg-grass text-ink' => $active === true,
                        'bg-ivory text-ink/70 ring-1 ring-line' => $active === false,
                        'bg-white text-ink/25 border border-dashed border-line' => $active === null,
                        'ring-2 ring-club/40' => $day->isSameDay(App\Clock::today()) && ! $active,
                    ]) title="{{ $day->format('l') }}{{ $active ? ': on the line' : '' }}">{{ Str::substr($day->format('D'), 0, 1) }}</span>
                </li>
            @endforeach
        </ol>
    </div>
</section>
