@php
    $user = $stats->user;
    $counts = $stats->resultCounts();
    $finished = array_sum($counts);
    $tones = [
        'short' => 'bg-red-400',
        'in_line' => 'bg-mint ring-1 ring-inset ring-club/30',
        'past' => 'bg-grass',
        'smashed' => 'bg-gold',
    ];
@endphp

<x-layouts.app :title="$user->name.' · GoalLine'">
    <x-slot:hero>
        <div class="mx-auto flex max-w-5xl flex-col gap-6 px-4 pt-6 pb-12 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex items-center gap-5">
                <x-avatar :user="$user" tone="light" class="size-20 text-2xl ring-4 ring-ivory/25 sm:size-24 sm:text-3xl" />
                <div class="min-w-0">
                    <h1 class="font-serif text-3xl font-medium tracking-tight text-ivory sm:text-4xl">{{ $user->name }}</h1>
                    <p class="mt-1 text-ivory/75">{{ '@'.$user->username }}</p>
                    @if ($user->bio)
                        <p class="mt-2 max-w-md text-ivory/90">{{ $user->bio }}</p>
                    @endif
                    <p class="mt-2 text-sm text-ivory/65">
                        @if ($user->location)
                            {{ $user->location }} &middot;
                        @endif
                        Member since {{ $user->created_at->format('F Y') }}
                    </p>
                </div>
            </div>
            <a href="{{ route('profile.edit') }}" class="shrink-0 self-start rounded-full border border-ivory/60 px-5 py-2 text-sm font-semibold text-ivory transition hover:border-ivory hover:bg-ivory hover:text-club sm:self-auto">
                Edit profile
            </a>
        </div>
    </x-slot:hero>

    {{-- Headline stats. --}}
    <dl class="grid grid-cols-2 overflow-hidden rounded-2xl border border-line bg-white lg:grid-cols-4">
        @foreach ([
            ['Points', $stats->points(), 'From GoalWeek results'],
            ['Goals completed', $stats->completedGoals()->count(), 'Of '.$stats->goalsStarted().' '.Str::plural('goal', $stats->goalsStarted()).' started'],
            ['In-line streak', $stats->currentStreak(), 'Best: '.$stats->bestStreak().' '.Str::plural('GoalWeek', $stats->bestStreak())],
            ['League record', implode('–', $stats->leagueRecord()), $stats->leagues()->isEmpty() ? 'Wins, ties, losses. Join a league to start.' : 'Wins, ties, losses across '.$stats->leagues()->count().' '.Str::plural('league', $stats->leagues()->count())],
        ] as [$label, $value, $note])
            <div class="border-line px-6 py-5 max-lg:odd:border-r max-lg:nth-[-n+2]:border-b lg:not-last:border-r">
                <dt class="eyebrow text-club">{{ $label }}</dt>
                <dd class="mt-2 font-serif text-3xl font-medium text-club tabular-nums">{{ $value }}</dd>
                <dd class="mt-1 text-sm text-ink/60">{{ $note }}</dd>
            </div>
        @endforeach
    </dl>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        {{-- Form guide: the latest finished GoalWeeks, like a league table. --}}
        <section class="rounded-2xl border border-line bg-white px-6 py-5">
            <h2 class="eyebrow text-club">Form</h2>
            @if ($stats->form()->isEmpty())
                <p class="mt-3 text-ink/60">Your last 10 GoalWeek results will show here once your first GoalWeek is final.</p>
            @else
                <ol class="mt-4 flex flex-wrap items-center gap-2" aria-label="Last {{ $stats->form()->count() }} GoalWeek results, oldest first">
                    @foreach ($stats->form() as $week)
                        <li class="size-7 rounded-md {{ $tones[$week->result()->value] }}" title="{{ $week->result()->label() }} · {{ $week->goal->title }}, {{ $week->starts_on->format('M j') }}">
                            <span class="sr-only">{{ $week->result()->label() }}</span>
                        </li>
                    @endforeach
                    <li class="ml-1 text-xs text-ink/50" aria-hidden="true">Latest &rarr;</li>
                </ol>
                <ul class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink/60">
                    @foreach (App\GoalWeekResult::cases() as $tier)
                        <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm {{ $tones[$tier->value] }}"></span>{{ $tier->label() }}</li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- GoalWeek result history. --}}
        <section class="rounded-2xl border border-line bg-white px-6 py-5">
            <div class="flex items-baseline justify-between">
                <h2 class="eyebrow text-club">GoalWeek results</h2>
                <span class="text-sm text-ink/50">{{ $finished }} finished</span>
            </div>
            <ul class="mt-4 space-y-3">
                @foreach (App\GoalWeekResult::cases() as $tier)
                    @php
                        $share = $finished ? $counts[$tier->value] / $finished * 100 : 0;
                    @endphp
                    <li>
                        <div class="flex justify-between text-sm">
                            <span class="text-ink/80">{{ $tier->label() }}</span>
                            <span class="font-semibold text-ink tabular-nums">{{ $counts[$tier->value] }}</span>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-ivory">
                            <div class="h-full rounded-full {{ $tones[$tier->value] }}" style="width: {{ $share }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    {{-- Trophy case. --}}
    <section class="mt-10">
        <h2 class="eyebrow text-club">Trophy case</h2>
        @if ($stats->completedGoals()->isEmpty())
            <div class="mt-3 overflow-hidden rounded-2xl border border-line bg-white">
                <div class="relative h-24 overflow-hidden bg-club">
                    <x-field-backdrop class="absolute inset-0 size-full" />
                </div>
                <x-goal-line />
                <p class="px-6 py-5 text-ink/70">When you cross the line on a goal, it lands here, with a photo and a few words if you want.</p>
            </div>
        @else
            <ul class="mt-3 grid gap-4 sm:grid-cols-2">
                @foreach ($stats->completedGoals() as $goal)
                    <li class="overflow-hidden rounded-2xl border border-line bg-white">
                        <a href="{{ route('goals.show', $goal) }}" class="block">
                            <div class="relative h-36 overflow-hidden bg-club">
                                @if ($goal->completionPhotoUrl())
                                    <img src="{{ $goal->completionPhotoUrl() }}" alt="" class="size-full object-cover">
                                @else
                                    <x-field-backdrop class="absolute inset-0 size-full" />
                                @endif
                                <span class="eyebrow absolute top-3 left-3 rounded-full bg-gold px-2.5 py-1 text-[0.65rem] text-ink">Completed</span>
                            </div>
                            <div class="px-5 py-4">
                                <h3 class="font-serif text-xl font-medium text-club">{{ $goal->title }}</h3>
                                <p class="mt-0.5 text-sm text-ink/55">
                                    {{ $goal->completed_at->format('F j, Y') }} &middot; {{ $goal->points() }} {{ Str::plural('point', $goal->points()) }}
                                </p>
                                @if ($goal->completion_note)
                                    <p class="mt-2 line-clamp-2 text-ink/75">&ldquo;{{ $goal->completion_note }}&rdquo;</p>
                                @endif
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Goals in progress. --}}
    <section class="mt-10">
        <div class="flex items-end justify-between">
            <h2 class="eyebrow text-club">Goals in progress</h2>
            <a href="{{ route('goals.create') }}" class="text-sm font-semibold text-club hover:underline">+ Add a goal</a>
        </div>
        @if ($stats->activeGoals()->isEmpty())
            <p class="mt-3 text-ink/60">No goals in progress.</p>
        @else
            <ul class="mt-3 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-white">
                @foreach ($stats->activeGoals() as $goal)
                    <li>
                        <a href="{{ route('goals.show', $goal) }}" class="flex items-center justify-between gap-4 px-6 py-4 hover:bg-mint/50">
                            <div class="min-w-0">
                                <p class="eyebrow text-[0.65rem] text-ink/50">{{ $goal->category->label() }}</p>
                                <p class="mt-0.5 font-serif text-lg font-medium text-club">{{ $goal->title }}</p>
                            </div>
                            <div class="shrink-0 text-right text-sm">
                                <p class="font-semibold text-ink tabular-nums">{{ $goal->points() }} {{ Str::plural('pt', $goal->points()) }}</p>
                                <p class="text-ink/55"><x-days-left :days="$goal->daysRemaining()" /></p>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Leagues and friends arrive next. --}}
    <section class="mt-10 grid gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-line bg-white px-6 py-5">
            <div class="flex items-baseline justify-between">
                <h2 class="eyebrow text-club">Leagues</h2>
                <a href="{{ route('leagues.index') }}" class="text-sm font-semibold text-club hover:underline">All leagues</a>
            </div>
            @forelse ($stats->leagues() as $league)
                <a href="{{ route('leagues.show', $league) }}" class="mt-2 flex items-center justify-between gap-3 text-ink hover:text-club">
                    <span class="truncate font-medium">{{ $league->name }}</span>
                    <span class="shrink-0 text-sm text-ink/55">{{ $league->members_count }} {{ Str::plural('member', $league->members_count) }} &middot; {{ $league->status()->label() }}</span>
                </a>
            @empty
                <p class="mt-2 text-ink/60">Not in a league yet. <a href="{{ route('leagues.index') }}" class="font-semibold text-club hover:underline">Start or join one</a>.</p>
            @endforelse
        </div>
        @foreach ([['Friends', 'People you follow and cheer on will show here.']] as [$heading, $text])
            <div class="rounded-2xl border border-dashed border-line px-6 py-5">
                <h2 class="eyebrow text-club">{{ $heading }}</h2>
                <p class="mt-2 text-ink/60">{{ $text }} <span class="font-semibold text-club">Coming soon.</span></p>
            </div>
        @endforeach
    </section>
</x-layouts.app>
