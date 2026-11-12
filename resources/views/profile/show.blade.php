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
        <div class="relative overflow-hidden">
            @if ($user->coverUrl())
                <img src="{{ $user->coverUrl() }}" alt="" class="absolute inset-0 size-full object-cover">
                <div class="absolute inset-0 bg-linear-to-t from-club via-club/75 to-club/30" aria-hidden="true"></div>
            @else
                <x-field-backdrop class="absolute inset-0 size-full" />
            @endif

            <div class="relative mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 pt-3 pb-3">
                <div class="flex min-w-0 items-center gap-3">
                    <x-avatar :user="$user" tone="light" class="size-14 shrink-0 text-lg ring-2 ring-ivory/40" />
                    <div class="min-w-0">
                        @if ($isOwn)
                            <p class="eyebrow text-[11px] text-mint">My Locker</p>
                        @endif
                        <h1 class="truncate text-[20px] leading-tight font-bold tracking-tight text-ivory md:text-[24px]">{{ $user->name }}</h1>
                        <p class="truncate text-[13px] text-ivory/85">{{ '@'.$user->username }}@if ($user->location) &middot; {{ $user->location }}@endif &middot; Member since {{ $user->created_at->format('F Y') }}</p>
                        @if ($user->bio)
                            <p class="line-clamp-2 text-[13px] text-ivory/95">{{ $user->bio }}</p>
                        @endif
                    </div>
                </div>
                @if ($isOwn)
                    <div class="flex shrink-0 flex-col items-end gap-1.5 sm:flex-row">
                        <a href="{{ route('profile.edit') }}" class="gl-tap relative inline-flex min-h-9 items-center rounded-full bg-ivory px-3 text-[13px] font-semibold text-club transition hover:bg-white">Edit profile</a>
                        <form method="POST" action="{{ route('logout') }}" class="md:hidden">
                            @csrf
                            <button type="submit" class="gl-tap relative min-h-8 rounded-full px-2 text-[13px] font-semibold text-ivory/85 hover:text-ivory">Log out</button>
                        </form>
                    </div>
                @else
                    <div class="flex shrink-0 flex-col items-end gap-1.5 sm:flex-row sm:items-center">
                        <x-nudge-button :user="$user" tone="light" />
                        <x-friend-button :user="$user" tone="light" />
                    </div>
                @endif
            </div>
        </div>
    </x-slot:hero>

    {{-- Headline stats. --}}
    <dl class="grid grid-cols-2 overflow-hidden rounded-2xl border border-line bg-white lg:grid-cols-4">
        @foreach ([
            ['Points', $stats->points(), 'From GoalWeek results'],
            ['Goals completed', $stats->completedGoals()->count(), 'Of '.$stats->goalsStarted().' '.Str::plural('goal', $stats->goalsStarted()).' started'],
            ['Weeks on the line', $stats->weeksOnTheLine(), $stats->weeksOnTheLine() === 0 ? 'Start your line this week. Best: '.$stats->bestStreak() : 'In a row at In line or better. Best: '.$stats->bestStreak()],
            ['League record', implode('–', $stats->leagueRecord()), $stats->leagues()->isEmpty() ? 'Wins, draws, losses. Join a GoalLeague to start.' : 'Wins, draws, losses across '.$stats->leagues()->count().' '.Str::plural('GoalLeague', $stats->leagues()->count())],
        ] as [$label, $value, $note])
            <div class="border-line px-4 py-3 max-lg:odd:border-r max-lg:nth-[-n+2]:border-b lg:not-last:border-r">
                <dt class="eyebrow text-club">{{ $label }}</dt>
                <dd class="mt-2 font-serif text-3xl font-bold text-club tabular-nums tracking-tight">{{ $value }}</dd>
                <dd class="mt-1 text-sm text-ink/70">{{ $note }}</dd>
            </div>
        @endforeach
    </dl>

    @if ($isOwn)
        <a href="{{ route('dashboard', ['tab' => 'history']) }}" class="mt-3 flex min-h-11 items-center justify-between rounded-xl border border-line bg-white px-3 text-[14px] hover:border-club/40" data-history-link>
            <span><span class="font-semibold text-club">History</span> <span class="text-ink/70">&middot; past seasons, goals and GoalWeeks</span></span>
            <x-icon name="chevron-right" class="size-4 text-ink/60" />
        </a>
    @endif

    @if ($featured = $user->featuredGoal)
        <section class="mt-6 flex flex-col gap-4 rounded-2xl border border-line bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="eyebrow text-club">Featured goal</p>
                <p class="mt-2 font-serif text-2xl font-bold text-club tracking-tight">
                    @if ($isOwn)
                        <a href="{{ route('goals.show', $featured) }}" class="hover:underline">{{ $featured->title }}</a>
                    @else
                        {{ $featured->title }}
                    @endif
                </p>
                <p class="mt-1 text-sm text-ink/70">
                    {{ $featured->category->label() }} &middot;
                    @if ($featured->isCompleted())
                        Completed {{ App\Clock::local($featured->completed_at)->format('F j, Y') }}
                    @else
                        <x-days-left :days="$featured->daysRemaining()" />, by {{ $featured->target_date->format('F j, Y') }}
                    @endif
                </p>
            </div>
            <div class="shrink-0 text-right">
                @if ($featured->isCompleted())
                    <span class="eyebrow rounded-full bg-gold px-3 py-1.5 text-[11px] text-ink">Completed</span>
                @else
                    <p class="font-serif text-3xl font-bold text-club tabular-nums tracking-tight">{{ $featured->points() }}</p>
                    <p class="text-sm text-ink/70">GoalWeek stars</p>
                @endif
            </div>
        </section>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        {{-- Form guide: the latest finished GoalWeeks, like a league table. --}}
        <section class="rounded-2xl border border-line bg-white px-4 py-3">
            <h2 class="eyebrow text-club">Form</h2>
            @if ($stats->form()->isEmpty())
                <p class="mt-3 text-ink/70">Your last 10 GoalWeek results will show here once your first GoalWeek is final.</p>
            @else
                <ol class="mt-4 flex flex-wrap items-center gap-2" aria-label="Last {{ $stats->form()->count() }} GoalWeek results, oldest first">
                    @foreach ($stats->form() as $week)
                        <li class="size-7 rounded-md {{ $tones[$week->result()->value] }}" title="{{ $week->result()->label() }} · {{ $week->goal->title }}, {{ $week->starts_on->format('M j') }}">
                            <span class="sr-only">{{ $week->result()->label() }}</span>
                        </li>
                    @endforeach
                    <li class="ml-1 text-xs text-ink/70" aria-hidden="true">Latest &rarr;</li>
                </ol>
                <ul class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink/70">
                    @foreach (App\GoalWeekResult::cases() as $tier)
                        <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm {{ $tones[$tier->value] }}"></span>{{ $tier->label() }}</li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- GoalWeek result history. --}}
        <section class="rounded-2xl border border-line bg-white px-4 py-3">
            <div class="flex items-baseline justify-between">
                <h2 class="eyebrow text-club">GoalWeek results</h2>
                <span class="text-sm text-ink/70">{{ $finished }} finished</span>
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

    <x-activity-heatmap :heatmap="$heatmap" class="mt-6" />

    <x-badge-shelf :user="$user" :is-own="$isOwn" class="mt-6" />

    {{-- Medals: earned by code from settled seasons. A Goal Achieved medal from a private Goal Day answer stays private. --}}
    @php
        $medals = $user->medals()->with('league', 'goal.result')->get()
            ->reject(fn ($medal) => ! $isOwn && $medal->type === App\MedalType::GoalAchieved && ! $medal->goal?->result?->shared)
            ->sortBy(fn ($medal) => $medal->rank())->values();
        $finales = $isOwn ? $user->goals()->has('result')->latest('target_date')->get() : collect();
    @endphp
    <section class="mt-6" aria-labelledby="locker-medals" data-locker-medals>
        <div class="flex items-center justify-between gap-3">
            <h2 id="locker-medals" class="eyebrow text-club">Medals</h2>
            <a href="{{ route('help', ['q' => 'medals']) }}" class="text-[13px] font-semibold text-club hover:underline">How medals work</a>
        </div>
        @if ($medals->isEmpty())
            <p class="mt-2 rounded-xl border border-line bg-white px-3 py-2.5 text-[14px] text-ink/75">Medals land here at the end of a season: Table Champion, Streak, Perfect Week and more.</p>
        @else
            <x-medal-shelf :medals="$medals" class="mt-2" />
        @endif
        @if ($finales->isNotEmpty())
            <div class="mt-2 flex flex-wrap gap-2 text-[13px]">
                @foreach ($finales as $finaleGoal)
                    <a href="{{ route('finale.show', ['goal' => $finaleGoal, 'skip' => 1]) }}" class="inline-flex min-h-9 items-center rounded-full border border-club/30 px-3 font-semibold text-club hover:border-club" data-replay-finale>Replay: {{ Str::limit($finaleGoal->title, 32) }}</a>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Trophy case. --}}
    <section class="mt-6">
        <h2 class="eyebrow text-club">Trophy case</h2>
        @if ($stats->completedGoals()->isEmpty())
            <div class="mt-3 overflow-hidden rounded-2xl border border-line bg-white">
                <div class="relative h-24 overflow-hidden bg-club">
                    <x-field-backdrop class="absolute inset-0 size-full" />
                </div>
                <x-goal-line />
                <p class="px-4 py-3 text-ink/70">When you cross the line on a goal, it lands here, with a photo and a few words if you want.</p>
            </div>
        @else
            <ul class="mt-3 grid gap-4 sm:grid-cols-2">
                @foreach ($stats->completedGoals() as $goal)
                    <li class="overflow-hidden rounded-2xl border border-line bg-white">
                        <a @if ($isOwn) href="{{ route('goals.show', $goal) }}" @endif class="block">
                            <div class="relative h-36 overflow-hidden bg-club">
                                @if ($goal->completionPhotoUrl())
                                    <img src="{{ $goal->completionPhotoUrl() }}" alt="" loading="lazy" decoding="async" class="size-full object-cover">
                                @else
                                    <x-field-backdrop class="absolute inset-0 size-full" />
                                @endif
                                <span class="eyebrow absolute top-3 left-3 rounded-full bg-gold px-2.5 py-1 text-[11px] text-ink">Completed</span>
                            </div>
                            <div class="px-4 py-3">
                                <h3 class="font-serif text-xl font-bold text-club tracking-tight">{{ $goal->title }}</h3>
                                <p class="mt-0.5 text-sm text-ink/70">
                                    {{ App\Clock::local($goal->completed_at)->format('F j, Y') }} &middot; {{ $goal->points() }} {{ Str::plural('point', $goal->points()) }}
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
    <section class="mt-6">
        <div class="flex items-end justify-between">
            <h2 class="eyebrow text-club">Goals in progress</h2>
            @if ($isOwn)
                <a href="{{ route('goals.create') }}" class="text-sm font-semibold text-club hover:underline">+ Add a goal</a>
            @endif
        </div>
        @if ($stats->activeGoals()->isEmpty())
            <p class="mt-3 text-ink/70">No goals in progress.</p>
        @else
            <ul class="mt-3 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-white">
                @foreach ($stats->activeGoals() as $goal)
                    <li>
                        <a @if ($isOwn) href="{{ route('goals.show', $goal) }}" @endif @class(['flex items-center justify-between gap-4 px-6 py-4', 'hover:bg-mint/50' => $isOwn])>
                            <div class="min-w-0">
                                <p class="eyebrow text-[11px] text-ink/70">{{ $goal->category->label() }}</p>
                                <p class="mt-0.5 font-serif text-lg font-bold text-club tracking-tight">{{ $goal->title }}</p>
                            </div>
                            <div class="shrink-0 text-right text-sm">
                                <p class="font-semibold text-ink tabular-nums">{{ $goal->points() }} {{ Str::plural('star', $goal->points()) }}</p>
                                <p class="text-ink/70"><x-days-left :days="$goal->daysRemaining()" /></p>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Leagues and friends arrive next. --}}
    <section class="mt-6 grid gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-line bg-white px-4 py-3">
            <div class="flex items-baseline justify-between">
                <h2 class="eyebrow text-club">GoalLeagues</h2>
                @if ($isOwn)
                    <a href="{{ route('leagues.index') }}" class="text-sm font-semibold text-club hover:underline">All GoalLeagues</a>
                @endif
            </div>
            @forelse ($stats->leagues() as $league)
                <a @can('view', $league) href="{{ route('leagues.show', $league) }}" @endcan class="mt-2 flex items-center justify-between gap-3 text-ink hover:text-club">
                    <span class="truncate font-medium">{{ $league->name }}</span>
                    <span class="shrink-0 text-sm text-ink/70">{{ $league->members_count }} {{ Str::plural('member', $league->members_count) }} &middot; {{ $league->status()->label() }}</span>
                </a>
            @empty
                <p class="mt-2 text-ink/70">Not in a GoalLeague yet.@if ($isOwn) <a href="{{ route('leagues.index') }}" class="font-semibold text-club hover:underline">Start or join one</a>.@endif</p>
            @endforelse
        </div>
        <div class="rounded-2xl border border-line bg-white px-4 py-3">
            <div class="flex items-baseline justify-between">
                <h2 class="eyebrow text-club">Friends</h2>
                @if ($isOwn)
                    <a href="{{ route('friends.index') }}" class="text-sm font-semibold text-club hover:underline">Add friends</a>
                @endif
            </div>
            @if ($friends->isEmpty())
                <p class="mt-2 text-ink/70">{{ $isOwn ? 'Add friends by their exact username or email.' : 'No friends to show yet.' }}</p>
            @else
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach ($friends as $friend)
                        <li>
                            <a href="{{ $friend->profileUrl() }}" class="flex items-center gap-2 rounded-full bg-ivory py-1 pr-3 pl-1 text-sm text-ink/80 hover:text-club">
                                <x-avatar :user="$friend" class="size-6 text-[11px]" /> {{ $friend->name }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    {{-- Recent highlights. --}}
    @if ($highlights->isNotEmpty())
        <section class="mt-6">
            <div class="flex items-baseline justify-between">
                <h2 class="eyebrow text-club">Highlights</h2>
                <a href="{{ route('highlights.index') }}" class="text-sm font-semibold text-club hover:underline">Open feed</a>
            </div>
            <div class="mt-3 grid gap-5 md:grid-cols-2">
                @foreach ($highlights as $highlight)
                    <x-highlight-card :highlight="$highlight" />
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
