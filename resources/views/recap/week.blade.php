@php
    $goalWeeks = $recap->goalWeeks();
    $badges = $recap->badgesEarned();
    $best = $recap->bestDay();
    $points = $goalWeeks->sum(fn ($row) => $row['result']?->points() ?? 0);
    $isThisWeek = $recap->sunday->gte(Carbon\CarbonImmutable::parse(App\Clock::today()->toDateString()));
@endphp

<x-layouts.app title="Your week · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-10">
            <p class="eyebrow text-mint">{{ $recap->monday->format('M j') }}&ndash;{{ $recap->sunday->format('M j') }}</p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">{{ $isThisWeek ? 'Your process this week' : 'Your process that week' }}</h1>
            <dl class="mt-6 grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach ([
                    ['Check-ins', $recap->checkIns(), 'tasks, habits, and challenges'],
                    ['Active days', $recap->activeDays().'/7', $best ? 'best: '.$best['date']->format('l').' ('.$best['count'].')' : 'every day counts'],
                    ['Points', $points, 'from your GoalWeeks'],
                    ['Streak', $recap->streak(), Str::plural('day', $recap->streak()).' in a row'],
                ] as [$label, $value, $note])
                    <div class="rounded-xl bg-ivory/10 px-4 py-3 ring-1 ring-ivory/15 ring-inset">
                        <dt class="eyebrow text-[0.62rem] text-mint">{{ $label }}</dt>
                        <dd class="mt-1 font-serif text-3xl font-medium text-ivory tabular-nums">{{ $value }}</dd>
                        <dd class="text-xs text-ivory/65">{{ $note }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </x-slot:hero>

    <div class="mx-auto max-w-3xl space-y-6">
        {{-- Day by day. --}}
        <section class="rounded-2xl border border-line bg-white px-5 py-4">
            <h2 class="eyebrow text-club">Day by day</h2>
            <ol class="mt-3 grid grid-cols-7 gap-2">
                @foreach (range(0, 6) as $offset)
                    @php
                        $date = $recap->monday->addDays($offset);
                        $count = $recap->daily->get($date->toDateString(), 0);
                    @endphp
                    <li class="flex flex-col items-center gap-1">
                        <span class="text-xs font-semibold text-ink/50">{{ $date->format('D') }}</span>
                        <span @class([
                            'flex aspect-square w-full max-w-12 items-center justify-center rounded-xl text-sm font-semibold tabular-nums',
                            'bg-club text-ivory' => $count >= 4,
                            'bg-grass text-white' => $count >= 2 && $count < 4,
                            'bg-mint text-club' => $count === 1,
                            'bg-ivory text-ink/30 ring-1 ring-line ring-inset' => $count === 0,
                        ])>{{ $count ?: '–' }}</span>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- GoalWeeks. --}}
        @if ($goalWeeks->isNotEmpty())
            <section>
                <h2 class="eyebrow text-club">{{ $isThisWeek ? 'Where your GoalWeeks stand' : 'Your GoalWeeks' }}</h2>
                <ul class="mt-3 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-white">
                    @foreach ($goalWeeks as $row)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                            <a href="{{ route('goals.show', $row['goal']) }}#goalweek-{{ $row['week']->position }}" class="min-w-0">
                                <span class="block truncate font-medium text-club hover:underline">{{ $row['goal']->title }}</span>
                                <span class="text-sm text-ink/55">{{ $row['week']->label() }}: {{ $row['week']->focus }}</span>
                            </a>
                            @if ($row['result'])
                                <x-goalweek-result :result="$row['result']" :close-call="$row['week']->isCloseCall()" />
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Wins. --}}
        @if ($badges->isNotEmpty() || $recap->cheersReceived() > 0)
            <section class="rounded-2xl border border-gold/50 bg-gold/10 px-5 py-4">
                <h2 class="eyebrow text-ink/70">Wins this week</h2>
                <ul class="mt-3 space-y-2">
                    @foreach ($badges as $badge)
                        <li class="flex items-center gap-3">
                            <x-badge :badge="$badge->badge" class="size-9" />
                            <span class="text-ink">Earned <span class="font-semibold">{{ $badge->badge->label() }}</span>{{ $badge->league ? ' in '.$badge->league->name : '' }}</span>
                        </li>
                    @endforeach
                    @if ($recap->cheersReceived() > 0)
                        <li class="flex items-center gap-3">
                            <span class="flex size-9 items-center justify-center rounded-full bg-grass text-white"><x-icon name="highlights" class="size-5" /></span>
                            <span class="text-ink">{{ $recap->cheersReceived() }} {{ Str::plural('cheer', $recap->cheersReceived()) }} on your posts</span>
                        </li>
                    @endif
                </ul>
            </section>
        @endif

        <section class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-club/30 bg-white px-5 py-4">
            <p class="text-ink/75">{{ $isThisWeek ? 'Next week starts Monday. Put every task on a day before it does.' : 'Keep the momentum going this week.' }}</p>
            <a href="{{ route('planner.show') }}" class="rounded-full bg-club px-5 py-2 text-sm font-semibold text-ivory hover:bg-club-dark">Plan my week</a>
        </section>

        <p class="text-center text-sm">
            @if ($isThisWeek)
                <a href="{{ route('recap.week', ['week' => 'last']) }}" class="font-semibold text-club hover:underline">See last week</a>
            @else
                <a href="{{ route('recap.week') }}" class="font-semibold text-club hover:underline">See this week</a>
            @endif
        </p>
    </div>
</x-layouts.app>
