<x-layouts.app title="GoalLeagues & Challenges · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <p class="eyebrow text-mint">Compete together</p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">GoalLeagues &amp; Challenges</h1>
            <p class="mt-3 max-w-xl text-ivory/85">Go head-to-head every GoalWeek, or take on a short challenge together. Friendly competition keeps you in line.</p>
            <div class="mt-7 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <a href="{{ route('leagues.create') }}" class="inline-flex items-center justify-center gap-2 rounded-full bg-ivory px-7 py-3.5 text-base font-semibold text-club shadow-lg shadow-black/10 transition hover:bg-white">
                    <x-icon name="leagues" class="size-5" /> Start a GoalLeague
                </a>
                <a href="{{ route('challenges.create') }}" class="inline-flex items-center justify-center gap-2 rounded-full bg-gold px-7 py-3.5 text-base font-semibold text-ink shadow-lg shadow-black/10 transition hover:brightness-105">
                    <x-icon name="flag" class="size-5" /> Start a challenge
                </a>
                <a href="#join" class="inline-flex items-center justify-center gap-2 rounded-full border-2 border-ivory/70 px-7 py-3 text-base font-semibold text-ivory transition hover:border-ivory hover:bg-ivory/10">
                    Join with a code
                </a>
            </div>
        </div>
    </x-slot:hero>

    {{-- Challenges. --}}
    <section class="mb-12">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="eyebrow flex items-center gap-1.5 text-club"><x-icon name="flag" class="size-4 text-gold" /> Challenges</h2>
                <p class="mt-1 text-sm text-ink/60">Short sprints with your crew. One check-in a day, a live leaderboard, and a badge for finishing.</p>
            </div>
            <a href="{{ route('challenges.create') }}" class="inline-flex items-center gap-1.5 rounded-full bg-gold px-4 py-2 text-sm font-semibold text-ink shadow-sm transition hover:brightness-105">
                <x-icon name="plus" class="size-4" /> Start a challenge
            </a>
        </div>

        @if ($challenges->isEmpty())
            <div class="mt-4 overflow-hidden rounded-2xl border border-gold/40 bg-white">
                <div class="relative bg-club px-6 py-6">
                    <x-field-backdrop class="absolute inset-0 size-full opacity-70" />
                    <p class="relative font-serif text-2xl font-medium text-ivory">&ldquo;No sugar for 30 days.&rdquo; &ldquo;Run 20 days this month.&rdquo;</p>
                    <p class="relative mt-1 text-ivory/80">Pick one thing, invite your people, and see who goes the distance.</p>
                </div>
                <x-goal-line />
            </div>
        @else
            <ul class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ($challenges as $challenge)
                    @php
                        $active = $challenge->isActive();
                        $percent = min(100, $challenge->my_days / max(1, $challenge->target_days) * 100);
                        $finished = $challenge->my_days >= $challenge->target_days;
                    @endphp
                    <li class="flex flex-col overflow-hidden rounded-2xl border border-line bg-white transition hover:border-gold/60">
                        <a href="{{ route('challenges.show', $challenge) }}" class="flex flex-1 flex-col gap-3 px-5 pt-5 pb-4">
                            <div class="flex items-start justify-between gap-3">
                                <x-badge :badge="App\Badge::ChallengeFinisher" :earned="$finished" class="size-11" />
                                <span @class([
                                    'eyebrow rounded-full px-2.5 py-1 text-[0.6rem]',
                                    'bg-club text-ivory' => $active,
                                    'bg-mint text-club' => ! $challenge->hasStarted(),
                                    'bg-ivory text-ink/55 ring-1 ring-line ring-inset' => $challenge->isOver(),
                                ])>
                                    @if (! $challenge->hasStarted())
                                        Starts {{ $challenge->starts_on->format('D, M j') }}
                                    @elseif ($challenge->isOver())
                                        Finished
                                    @else
                                        Day {{ $challenge->dayNumber() }} of {{ $challenge->lengthInDays() }}
                                    @endif
                                </span>
                            </div>
                            <div class="min-w-0">
                                <p class="font-serif text-xl font-medium text-balance text-club">{{ $challenge->title }}</p>
                                <p class="mt-0.5 text-sm text-ink/55">{{ $challenge->members_count }} {{ Str::plural('member', $challenge->members_count) }} &middot; finish with {{ $challenge->target_days }} days</p>
                            </div>
                            <div>
                                <div class="flex justify-between text-xs text-ink/60">
                                    <span>Your progress</span>
                                    <span class="font-semibold text-ink tabular-nums">{{ $challenge->my_days }}/{{ $challenge->target_days }}</span>
                                </div>
                                <div class="mt-1 h-2 overflow-hidden rounded-full bg-mint">
                                    <div @class(['h-full rounded-full', 'bg-gold' => $finished, 'bg-grass' => ! $finished]) style="width: {{ $percent }}%"></div>
                                </div>
                            </div>
                        </a>
                        @if ($active)
                            <form method="POST" action="{{ route('challenges.check-in', $challenge) }}" class="border-t border-line">
                                @csrf
                                <input type="hidden" name="return" value="list">
                                <button type="submit" @class([
                                    'flex w-full items-center justify-center gap-2 px-5 py-3 text-sm font-semibold transition',
                                    'bg-grass/10 text-club' => $challenge->checked_today,
                                    'text-club hover:bg-mint' => ! $challenge->checked_today,
                                ])>
                                    @if ($challenge->checked_today)
                                        <svg viewBox="0 0 16 16" class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7" /></svg>
                                        Done today &middot; tap to undo
                                    @else
                                        I did it today
                                    @endif
                                </button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('challenges.find') }}" class="mt-4 flex max-w-md gap-2">
            @csrf
            <label for="challenge_code" class="sr-only">Challenge code</label>
            <input id="challenge_code" name="code" required placeholder="Have a challenge code?" autocomplete="off" autocapitalize="characters"
                class="min-w-0 flex-1 rounded-lg border border-line bg-white px-3.5 py-2 font-mono text-sm tracking-widest uppercase outline-none placeholder:font-sans placeholder:tracking-normal placeholder:normal-case focus:border-club focus:ring-4 focus:ring-club/10">
            <button type="submit" class="rounded-lg border border-club/30 px-4 py-2 text-sm font-semibold text-club hover:border-club">Join</button>
        </form>
        @error('challenge_code')
            <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </section>

    <h2 class="eyebrow mb-3 text-club">GoalLeagues</h2>
    <div class="grid gap-4 sm:grid-cols-2">
        <a href="{{ route('leagues.create') }}" class="group rounded-2xl border border-line bg-white px-6 py-5 transition hover:border-club/40">
            <p class="eyebrow text-club">Start a GoalLeague</p>
            <p class="mt-2 font-serif text-xl font-medium text-club">Be the commissioner</p>
            <p class="mt-1 text-ink/65">Pick a kickoff Monday and a season end, then invite your people.</p>
            <span class="mt-3 inline-block text-sm font-semibold text-club group-hover:underline">Create a GoalLeague &rarr;</span>
        </a>

        <form id="join" method="POST" action="{{ route('leagues.find') }}" class="scroll-mt-6 rounded-2xl border border-line bg-white px-6 py-5">
            @csrf
            <p class="eyebrow text-club">Join a GoalLeague</p>
            <label for="code" class="mt-2 block font-serif text-xl font-medium text-club">Have an invite code?</label>
            <div class="mt-3 flex gap-2">
                <input id="code" name="code" value="{{ old('code') }}" required placeholder="K7QX-M4PA" autocomplete="off" autocapitalize="characters"
                    class="min-w-0 flex-1 rounded-lg border border-line bg-white px-3.5 py-2.5 font-mono tracking-widest uppercase outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                <button type="submit" class="rounded-lg bg-club px-4 py-2.5 font-semibold text-ivory hover:bg-club-dark">Find</button>
            </div>
            @error('code')
                <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </form>
    </div>

    <section class="mt-10">
        <h2 class="eyebrow text-club">Your GoalLeagues</h2>
        @if ($leagues->isEmpty())
            <div class="mt-3 overflow-hidden rounded-2xl border border-line bg-white">
                <div class="relative h-24 overflow-hidden bg-club">
                    <x-field-backdrop class="absolute inset-0 size-full" />
                </div>
                <x-goal-line />
                <p class="px-6 py-5 text-ink/70">You're not in a GoalLeague yet. GoalLeagues are optional: start one with friends, teammates, or classmates, or join with a code.</p>
            </div>
        @else
            <ul class="mt-3 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-white">
                @foreach ($leagues as $league)
                    <li>
                        <a href="{{ route('leagues.show', $league) }}" class="flex items-center justify-between gap-4 px-6 py-4 hover:bg-mint/50">
                            <div class="min-w-0">
                                <p class="font-serif text-lg font-medium text-club">{{ $league->name }}</p>
                                <p class="text-sm text-ink/60">
                                    {{ $league->members->count() }} {{ Str::plural('member', $league->members->count()) }} &middot;
                                    @if ($league->status() === App\LeagueStatus::Forming)
                                        Kicks off {{ $league->starts_on->format('M j') }}
                                    @elseif ($league->status() === App\LeagueStatus::Active)
                                        Week {{ $league->currentWeek() }} of {{ $league->weeksCount() }}
                                    @else
                                        Season over
                                    @endif
                                </p>
                            </div>
                            @php
                                $unread = $league->memberFor(auth()->user())?->unreadCount() ?? 0;
                            @endphp
                            @if ($unread > 0)
                                <span class="shrink-0 rounded-full bg-club px-2 py-0.5 text-xs font-semibold text-ivory tabular-nums" title="Unread chat messages">{{ $unread }} new</span>
                            @endif
                            <span @class([
                                'eyebrow shrink-0 rounded-full px-2.5 py-1 text-[0.65rem]',
                                'bg-mint text-club' => $league->status() === App\LeagueStatus::Forming,
                                'bg-grass text-white' => $league->status() === App\LeagueStatus::Active,
                                'bg-ivory text-ink/60' => $league->status() === App\LeagueStatus::Finished,
                            ])>{{ $league->status()->label() }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.app>
