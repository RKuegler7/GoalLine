{{-- A member's profile (My Locker on your own). The top is who they are: cover, photo, name, a tappable counts row,
     bio and identity, current goals. Heavier data (stats, trophy case, history) lives lower, in sections reachable by
     the sticky chips. Friends are mutual (both agree), so there's one Friends list, never Followers and Following:
     those would be the same people. Every number comes from the same services as the standings and History. --}}
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
    $completed = $stats->completedGoals();
    $finales = $isOwn ? $user->goals()->has('result')->latest('target_date')->get() : collect();
    $sections = [['about', 'About'], ['goals', 'Goals'], ['highlights', 'Highlights'], ['stats', 'Stats'], ['trophy-case', 'Trophy case'], ['history', 'History']];
@endphp

<x-layouts.app :title="$user->name.' · GoalLine'">
    <x-slot:hero>
        {{-- Cover (or the green field), with the photo overlapping it. --}}
        <div class="relative h-28 overflow-hidden sm:h-36" data-profile-cover>
            @if ($user->coverUrl())
                <img src="{{ $user->coverUrl() }}" alt="" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover">
                <div class="absolute inset-0 bg-linear-to-t from-club/80 to-transparent" aria-hidden="true"></div>
            @else
                <x-field-backdrop class="absolute inset-0 size-full" />
            @endif
        </div>

        <div class="relative mx-auto max-w-5xl px-4 pb-4">
            <div class="-mt-10 flex items-end justify-between gap-3">
                <x-avatar :user="$user" tone="light" class="size-20 shrink-0 text-2xl ring-4 ring-club" />
                <div class="flex shrink-0 flex-wrap items-center justify-end gap-1.5 pb-1">
                    @if ($isOwn)
                        <a href="{{ route('profile.edit') }}" class="gl-tap relative inline-flex min-h-9 items-center rounded-full bg-ivory px-3 text-[13px] font-semibold text-club transition hover:bg-white">Edit profile</a>
                        <form method="POST" action="{{ route('logout') }}" class="md:hidden">
                            @csrf
                            <button type="submit" class="gl-tap relative min-h-9 rounded-full px-2 text-[13px] font-semibold text-ivory/85 hover:text-ivory">Log out</button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- The name never gets cut off: it wraps (up to three lines) and only then ends in an ellipsis. --}}
            <div class="mt-2 min-w-0" data-profile-identity>
                @if ($isOwn)
                    <p class="eyebrow text-[11px] text-mint">My Locker</p>
                @endif
                <h1 class="line-clamp-3 text-[22px] leading-tight font-bold tracking-tight [overflow-wrap:anywhere] text-ivory md:text-[26px]" data-profile-name>{{ $user->name }}</h1>
                <p class="mt-0.5 text-[13px] text-ivory/85 [overflow-wrap:anywhere]">
                    {{ '@'.$user->username }}@if ($user->location) &middot; {{ $user->location }}@endif
                </p>
            </div>

            @unless ($isOwn)
                {{-- Compact: the relationship button and Nudge side by side under the name, one short line below. --}}
                <div class="mt-3" data-profile-actions>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-friend-button :user="$user" tone="light" />
                        <x-nudge-button :user="$user" tone="light" />
                        @if (App\Models\UserMute::where('user_id', auth()->id())->where('muted_user_id', $user->id)->exists())
                            <form method="POST" action="{{ route('users.mute', $user) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="min-h-11 rounded-full border border-ivory/60 px-4 text-sm font-semibold text-ivory hover:bg-ivory/10" data-unmute>Muted &middot; Unmute</button>
                            </form>
                        @endif
                    </div>
                    <p class="mt-1 text-[11px] text-ivory/80" data-friend-note>Friends can see your League + friends posts.</p>
                </div>
            @endunless
        </div>
    </x-slot:hero>

    {{-- Counts: always at the top, each one tappable. --}}
    <nav aria-label="{{ $user->name }} at a glance" class="-mt-1 grid grid-cols-4 gap-1.5" data-profile-counts>
        @foreach ([
            ['Friends', $friendCount, '#profile-friends', 'friends'],
            ['Goals done', $completed->count(), '#trophy-case', 'goals-completed'],
            ['Medals', $medals->count(), '#trophy-case', 'medals'],
            ['On the line', $stats->weeksOnTheLine(), '#stats', 'weeks-on-the-line'],
        ] as [$label, $value, $href, $key])
            {{-- In-page links: just a scroll, never a page visit (a visit would redraw the page and close what's open). --}}
            <a href="{{ $href }}" data-turbo="false" @if ($key === 'friends') data-open-friends @endif data-count="{{ $key }}"
                class="flex min-h-14 flex-col items-center justify-center rounded-xl border border-line bg-white px-1 py-1.5 text-center hover:border-club/40">
                <span class="text-[18px] leading-tight font-bold text-ink tabular-nums">{{ $value }}</span>
                <span class="text-[11px] leading-tight font-semibold text-ink/70">{{ $label }}</span>
            </a>
        @endforeach
    </nav>
    @if (! $isOwn && $mutualFriends > 0)
        <p class="mt-1.5 text-center text-[13px] text-ink/75" data-mutual-friends>{{ $mutualFriends }} mutual {{ Str::plural('friend', $mutualFriends) }}</p>
    @endif

    {{-- Section chips: stay at the top while scrolling. --}}
    <nav aria-label="Profile sections" class="sticky top-[calc(3.5rem+env(safe-area-inset-top))] z-20 -mx-4 mt-3 flex gap-1.5 overflow-x-auto bg-ivory/95 px-4 py-2 backdrop-blur [scrollbar-width:none] md:top-16" data-profile-chips>
        @foreach ($sections as [$id, $label])
            <a href="#{{ $id }}" data-turbo="false" class="inline-flex min-h-9 shrink-0 items-center rounded-full bg-white px-3 text-[13px] font-semibold text-club ring-1 ring-line hover:ring-club/40">{{ $label }}</a>
        @endforeach
    </nav>

    {{-- About: bio and identity, then friends and shared leagues. --}}
    <section id="about" class="mt-3 scroll-mt-28 space-y-3" aria-labelledby="about-title">
        <h2 id="about-title" class="sr-only">About</h2>
        @if ($user->bio || $user->why_here || $user->interests || $isOwn)
            <div class="rounded-2xl border border-line bg-white px-4 py-3" data-profile-about>
                @if ($user->bio)
                    <p class="text-[15px] text-ink [overflow-wrap:anywhere]">{{ $user->bio }}</p>
                @elseif ($isOwn)
                    <p class="text-[14px] text-ink/70">Add a short bio so friends know what you're chasing. <a href="{{ route('profile.edit') }}" class="font-semibold text-club hover:underline">Edit profile</a></p>
                @endif
                @if ($user->why_here)
                    <p class="mt-2 text-[14px] text-ink/80"><span class="font-semibold text-club">Why I'm here:</span> {{ $user->why_here }}</p>
                @endif
                <p class="mt-2 text-[13px] text-ink/70">
                    @if ($user->location){{ $user->location }} &middot; @endif Joined {{ $user->created_at->format('F Y') }}
                </p>
                @if ($user->interests)
                    <ul class="mt-2 flex flex-wrap gap-1.5" aria-label="Interests" data-profile-interests>
                        @foreach ($user->interests as $interest)
                            <li class="rounded-full bg-mint px-2.5 py-0.5 text-[12px] font-semibold text-club">{{ $interest }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        <div class="grid gap-3 sm:grid-cols-2">
            <div id="profile-friends" class="scroll-mt-28 rounded-2xl border border-line bg-white px-4 py-3" data-profile-friends>
                <div class="flex items-baseline justify-between">
                    <h3 class="eyebrow text-club">Friends</h3>
                    @if ($isOwn)
                        <a href="{{ route('friends.index') }}" class="text-sm font-semibold text-club hover:underline">Add friends</a>
                    @endif
                </div>
                @if ($canSeeFriends)
                    {{-- Expands in place: the first page loads when opened, then "Show more" and search, no page reload. --}}
                    <details class="mt-2" data-friends-expand data-src="{{ route('profiles.friends', ['user' => $user, 'partial' => 1]) }}">
                        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between rounded-lg px-1 font-semibold text-ink hover:bg-mint/40 [&::-webkit-details-marker]:hidden">
                            <span>Friends ({{ $friendCount }})</span>
                            <x-icon name="chevron-down" class="size-4 text-ink/60" />
                        </summary>
                        @if ($friendCount > App\Http\Controllers\ProfileFriendsController::PER_PAGE)
                            <label for="profile-friend-search" class="sr-only">Search friends</label>
                            <input id="profile-friend-search" type="search" data-friends-search placeholder="Search by name or username"
                                class="mt-1 w-full rounded-lg border border-line bg-ivory px-3 py-2 text-base outline-none focus:border-club">
                        @endif
                        <div data-friends-body class="mt-1"><x-line-loader size="inline" label="Loading friends" /></div>
                        <a href="{{ route('profiles.friends', $user) }}" class="mt-1 inline-flex min-h-10 items-center text-[13px] font-semibold text-club hover:underline">Open the full list</a>
                    </details>
                @else
                    <p class="mt-2 text-[14px] text-ink/70">{{ $mutualFriends > 0 ? $mutualFriends.' mutual '.Str::plural('friend', $mutualFriends).'. ' : '' }}{{ $user->name }} keeps their friends list private.</p>
                @endif
                <p class="mt-2 text-[12px] text-ink/70">Friends are mutual: you both agree.</p>
            </div>

            <div class="rounded-2xl border border-line bg-white px-4 py-3">
                <h3 class="eyebrow text-club">{{ $isOwn ? 'Your GoalLeagues' : 'GoalLeagues you share' }}</h3>
                @php $leagueRows = $isOwn ? $stats->leagues()->map(fn ($league) => ['league' => $league, 'goal' => null]) : $mutualLeagues; @endphp
                @forelse ($leagueRows as $row)
                    <a href="{{ route('leagues.show', $row['league']) }}" class="flex min-h-11 items-center justify-between gap-3 hover:text-club" @unless ($isOwn) data-mutual-leagues @endunless>
                        <span class="truncate font-medium text-ink">{{ $row['league']->name }}</span>
                        <span class="shrink-0 truncate text-[13px] text-ink/70">{{ $row['goal'] ? '“'.Str::limit($row['goal'], 28).'”' : $row['league']->status()->label() }}</span>
                    </a>
                @empty
                    <p class="mt-2 text-[14px] text-ink/70">{{ $isOwn ? 'Not in a GoalLeague yet.' : 'No GoalLeagues in common yet.' }}</p>
                @endforelse
            </div>
        </div>
    </section>

    {{-- Goals: what they're working on now (each goal's own visibility applies). --}}
    <section id="goals" class="mt-6 scroll-mt-28" aria-labelledby="goals-title">
        <div class="flex items-end justify-between">
            <h2 id="goals-title" class="eyebrow text-club">Current goals</h2>
            @if ($isOwn)
                <a href="{{ route('goals.create') }}" class="text-sm font-semibold text-club hover:underline">+ Add a goal</a>
            @endif
        </div>

        @if ($featured = $user->featuredGoal)
            @if ($featured->isVisibleOnProfileTo(auth()->user()))
                <p class="mt-2 text-[13px] text-ink/75"><span class="eyebrow text-[11px] text-club">Featured goal</span> &middot; {{ $featured->title }}</p>
            @endif
        @endif

        @if ($currentGoals->isEmpty())
            <p class="mt-2 rounded-xl border border-line bg-white px-3 py-2.5 text-[14px] text-ink/70">{{ $isOwn ? 'No goals in progress. Set one and it shows here.' : 'No goals to show right now.' }}</p>
        @else
            <ul class="mt-2 grid gap-2 sm:grid-cols-2" data-profile-goals>
                @foreach ($currentGoals as $goal)
                    @php
                        $total = max(1, $goal->weeks->count());
                        $played = $goal->weeks->filter->isPast()->count();
                        $league = $goal->leagues->sortByDesc('starts_on')->first();
                    @endphp
                    <li class="rounded-2xl border border-line bg-white px-4 py-3" data-profile-goal>
                        <a @if ($isOwn) href="{{ route('goals.show', $goal) }}" @endif class="block">
                            <p class="eyebrow text-[11px] text-ink/70">{{ $goal->category->label() }}@if ($isOwn && $goal->visibility !== App\GoalVisibility::FriendsAndLeagues) &middot; {{ $goal->visibility->label() }}@endif</p>
                            <p class="mt-0.5 text-[16px] leading-snug font-bold text-club [overflow-wrap:anywhere]">{{ $goal->title }}</p>
                            <x-goal-progress class="mt-2" :percent="$played / $total * 100" label="GoalWeeks played" :name="'profile-goal-'.$goal->id" />
                            <p class="mt-1 text-[12px] text-ink/70">
                                {{ $played }} of {{ $total }} {{ Str::plural('GoalWeek', $total) }} &middot; by {{ $goal->target_date->format('M j, Y') }}
                                @if ($league) &middot; {{ $league->name }}@endif
                                &middot; {{ $goal->isBeforeKickoff() ? 'Kicks off soon' : 'In progress' }}
                            </p>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Highlights: recent posts this viewer may see. --}}
    <section id="highlights" class="mt-6 scroll-mt-28" aria-labelledby="highlights-title">
        <div class="flex items-baseline justify-between">
            <h2 id="highlights-title" class="eyebrow text-club">Highlights</h2>
            <a href="{{ route('highlights.index') }}" class="text-sm font-semibold text-club hover:underline">Open feed</a>
        </div>
        @if ($highlights->isEmpty())
            <p class="mt-2 rounded-xl border border-line bg-white px-3 py-2.5 text-[14px] text-ink/70">No Highlights to show yet.</p>
        @else
            <ul class="mt-2 grid grid-cols-3 gap-1.5" data-profile-highlights>
                @foreach ($highlights as $highlight)
                    <li>
                        <a href="{{ route('highlights.index') }}#highlight-{{ $highlight->id }}" class="relative block aspect-square overflow-hidden rounded-lg bg-club" title="{{ $highlight->caption ?? $highlight->headline }}">
                            @if ($highlight->photoUrl())
                                <img src="{{ $highlight->photoUrl() }}" alt="{{ $highlight->caption ?? $highlight->headline }}" loading="lazy" decoding="async" class="size-full object-cover">
                            @else
                                <span class="flex size-full flex-col justify-end bg-linear-to-br from-club to-club-dark p-2 text-left">
                                    <span class="eyebrow text-[9px] text-mint">{{ $highlight->kind->label() }}</span>
                                    <span class="line-clamp-3 text-[12px] leading-tight font-semibold text-ivory">{{ $highlight->headline }}</span>
                                </span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Stats: the same numbers as the standings, per shared GoalLeague, then form and the year of check-ins. --}}
    <section id="stats" class="mt-6 scroll-mt-28" aria-labelledby="stats-title">
        <h2 id="stats-title" class="eyebrow text-club">Stats</h2>
        <dl class="mt-2 grid grid-cols-3 gap-1.5">
            @foreach ([
                ['Stars', $stats->points()],
                ['Weeks on the line', $stats->weeksOnTheLine().' · best '.$stats->bestStreak()],
                ['League record', implode('–', $stats->leagueRecord())],
            ] as [$label, $value])
                <div class="rounded-xl border border-line bg-white px-2.5 py-2">
                    <dt class="truncate text-[11px] font-semibold text-ink/70">{{ $label }}</dt>
                    <dd class="text-[16px] leading-tight font-bold text-ink tabular-nums">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>

        @include('profile.partials.league-stats')

        <div class="mt-3 rounded-2xl border border-line bg-white px-4 py-3">
            <h3 class="text-[13px] font-semibold text-ink/80">Form and GoalWeek results</h3>
            @if ($stats->form()->isEmpty())
                <p class="mt-2 text-[14px] text-ink/70">The last 10 GoalWeek results show here once the first GoalWeek is final.</p>
            @else
                <ol class="mt-2 flex flex-wrap items-center gap-1.5" aria-label="Last {{ $stats->form()->count() }} GoalWeek results, oldest first">
                    @foreach ($stats->form() as $week)
                        <li class="size-6 rounded-md {{ $tones[$week->result()->value] }}" title="{{ $week->result()->label() }} · {{ $week->goal->title }}, {{ $week->starts_on->format('M j') }}">
                            <span class="sr-only">{{ $week->result()->label() }}</span>
                        </li>
                    @endforeach
                </ol>
                <p class="mt-2 text-[12px] text-ink/70">
                    @foreach (App\GoalWeekResult::cases() as $tier)
                        {{ $tier->label() }} <span class="font-semibold tabular-nums">{{ $counts[$tier->value] }}</span>@if (! $loop->last) &middot; @endif
                    @endforeach
                    &middot; {{ $finished }} finished
                </p>
            @endif
        </div>

        <x-activity-heatmap :heatmap="$heatmap" class="mt-3" />
    </section>

    {{-- Trophy case: medals, badges and awards, and goals completed. --}}
    <section id="trophy-case" class="mt-6 scroll-mt-28" aria-labelledby="trophy-title">
        <div class="flex items-center justify-between gap-3">
            <h2 id="trophy-title" class="eyebrow text-club">Trophy case</h2>
            <a href="{{ route('help', ['q' => 'medals']) }}" class="text-[13px] font-semibold text-club hover:underline">How medals work</a>
        </div>
        <div class="mt-2" data-locker-medals>
            @if ($medals->isEmpty())
                <p class="rounded-xl border border-line bg-white px-3 py-2.5 text-[14px] text-ink/75">Medals land here at the end of a season: Table Champion, Streak, Perfect Week and more.</p>
            @else
                <x-medal-shelf :medals="$medals" />
            @endif
        </div>

        <x-badge-shelf :user="$user" :is-own="$isOwn" class="mt-3" />

        @if ($completed->isNotEmpty())
            <ul class="mt-3 grid gap-3 sm:grid-cols-2" data-completed-goals>
                @foreach ($completed as $goal)
                    <li class="overflow-hidden rounded-2xl border border-line bg-white">
                        <a @if ($isOwn) href="{{ route('goals.show', $goal) }}" @endif class="flex items-center gap-3 p-3">
                            <span class="relative size-14 shrink-0 overflow-hidden rounded-xl bg-club">
                                @if ($goal->completionPhotoUrl())
                                    <img src="{{ $goal->completionPhotoUrl() }}" alt="" loading="lazy" decoding="async" class="size-full object-cover">
                                @else
                                    <x-field-backdrop class="absolute inset-0 size-full" />
                                @endif
                            </span>
                            <span class="min-w-0">
                                <span class="eyebrow block text-[10px] text-gold-ink">Completed</span>
                                <span class="block text-[15px] font-bold text-club [overflow-wrap:anywhere]">{{ $goal->title }}</span>
                                <span class="block text-[12px] text-ink/70">{{ App\Clock::local($goal->completed_at)->format('F j, Y') }} &middot; {{ $goal->points() }} {{ Str::plural('star', $goal->points()) }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @elseif ($isOwn)
            <p class="mt-3 rounded-xl border border-line bg-white px-3 py-2.5 text-[14px] text-ink/70">When you cross the line on a goal, it lands here.</p>
        @endif
    </section>

    {{-- History: past GoalLeagues and Challenges with the outcomes they chose to share. --}}
    <section id="history" class="mt-6 scroll-mt-28" aria-labelledby="history-title">
        <h2 id="history-title" class="eyebrow text-club">History</h2>
        @if ($history['leagues']->isEmpty() && $history['challenges']->isEmpty())
            <p class="mt-2 rounded-xl border border-line bg-white px-3 py-2.5 text-[14px] text-ink/70">Finished seasons and Challenges show here.</p>
        @else
            <ul class="mt-2 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-white" data-profile-history>
                @foreach ($history['leagues'] as $row)
                    <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                        <span class="min-w-0">
                            <span class="block truncate text-[14px] font-semibold text-ink">{{ $row['league']->name }}</span>
                            <span class="block text-[12px] text-ink/70">GoalLeague &middot; {{ $row['league']->ends_on->format('M Y') }}@if ($row['outcome']) &middot; {{ $row['outcome'] }}@endif</span>
                        </span>
                        @if ($row['rank'])
                            <span class="shrink-0 text-[13px] font-bold text-club tabular-nums">{{ $row['rank'] }} of {{ $row['size'] }}</span>
                        @endif
                    </li>
                @endforeach
                @foreach ($history['challenges'] as $row)
                    <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                        <span class="min-w-0">
                            <span class="block truncate text-[14px] font-semibold text-ink">{{ $row['challenge']->title }}</span>
                            <span class="block text-[12px] text-ink/70">Challenge &middot; {{ $row['challenge']->ends_on->format('M Y') }}{{ $row['finished'] ? ' · Finished it' : '' }}</span>
                        </span>
                        @if ($row['rank'])
                            <span class="shrink-0 text-[13px] font-bold text-club tabular-nums">{{ $row['rank'] }} of {{ $row['size'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($isOwn)
            <a href="{{ route('dashboard', ['tab' => 'history']) }}" class="mt-2 flex min-h-11 items-center justify-between rounded-xl border border-line bg-white px-3 text-[14px] hover:border-club/40" data-history-link>
                <span><span class="font-semibold text-club">History</span> <span class="text-ink/70">&middot; past seasons, goals and GoalWeeks</span></span>
                <x-icon name="chevron-right" class="size-4 text-ink/60" />
            </a>
            @if ($finales->isNotEmpty())
                <div class="mt-2 flex flex-wrap gap-2 text-[13px]">
                    @foreach ($finales as $finaleGoal)
                        <a href="{{ route('finale.show', ['goal' => $finaleGoal, 'skip' => 1]) }}" class="inline-flex min-h-9 items-center rounded-full border border-club/30 px-3 font-semibold text-club hover:border-club" data-replay-finale>Replay: {{ Str::limit($finaleGoal->title, 32) }}</a>
                    @endforeach
                </div>
            @endif
        @endif
    </section>
</x-layouts.app>
