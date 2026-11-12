{{-- The Matchups tab when there's no matchup this week: never a dead end. It explains what a matchup is, offers the
     next step for the member's situation, and shows their own week against the line. --}}
<x-layouts.app title="Matchups · GoalLine">
    <x-slot:hero>
        <x-page-header title="Matchups" subtitle="Your week against a friend, scored against your own plans." />
    </x-slot:hero>

    <div class="space-y-4" data-first-content data-matchup-hub="{{ $state }}">
        <section class="overflow-hidden rounded-2xl border border-club/20 bg-white">
            <div class="gl-field relative px-5 py-6">
                @switch($state)
                    @case('kickoff')
                        <p class="eyebrow text-mint">Season not started</p>
                        <h2 class="mt-1 text-[22px] leading-tight font-bold text-ivory">{{ $forming->name }} kicks off {{ $forming->starts_on->format('l, M j') }}.</h2>
                        <p class="mt-1 text-[14px] text-ivory/90">Your first matchup goes live that Monday. Until then, bring a friend or two: every member makes the season better.</p>
                        @break
                    @case('season-over')
                        <p class="eyebrow text-mint">Season over</p>
                        <h2 class="mt-1 text-[22px] leading-tight font-bold text-ivory">{{ $finished->name }} is in the books.</h2>
                        <p class="mt-1 text-[14px] text-ivory/90">Every week you played is saved in History. Run it back with the same crew, or start something new.</p>
                        @break
                    @default
                        <p class="eyebrow text-mint">No matchup yet</p>
                        <h2 class="mt-1 text-[22px] leading-tight font-bold text-ivory">No Matchup yet.</h2>
                        <p class="mt-1 text-[14px] text-ivory/90">A Matchup is your week against a friend, scored against your own plans.</p>
                @endswitch
            </div>

            <div class="grid gap-2 px-4 py-4 sm:grid-cols-2">
                @if ($state === 'kickoff')
                    <a href="{{ route('leagues.show', $forming) }}#invite" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-grass px-5 text-[15px] font-bold text-ink hover:brightness-105">
                        <x-icon name="users" class="size-5" /> Invite friends
                    </a>
                    <a href="{{ route('leagues.show', $forming) }}" class="inline-flex min-h-12 items-center justify-center rounded-full border border-club/30 px-5 text-[15px] font-semibold text-club hover:border-club">Open {{ Str::limit($forming->name, 24) }}</a>
                @else
                    @if ($state === 'season-over')
                        <a href="{{ route('dashboard', ['tab' => 'history']) }}" class="inline-flex min-h-12 items-center justify-center rounded-full border border-club/30 px-5 text-[15px] font-semibold text-club hover:border-club">See it in History</a>
                    @endif
                    <a href="{{ route('leagues.create') }}" data-open-sheet="start-sheet" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-grass px-5 text-[15px] font-bold text-ink hover:brightness-105">
                        <x-icon name="plus" class="size-5" /> Start a GoalLeague
                    </a>
                    <a href="{{ route('leagues.index', ['tab' => 'invites']) }}#join" class="inline-flex min-h-12 items-center justify-center rounded-full border border-club/30 px-5 text-[15px] font-semibold text-club hover:border-club">Join with a code</a>
                    @if (Route::has('highlights.explore'))
                        <a href="{{ route('highlights.index', ['tab' => 'explore']) }}" class="inline-flex min-h-12 items-center justify-center rounded-full border border-club/30 px-5 text-[15px] font-semibold text-club hover:border-club">Explore GoalLeagues</a>
                    @endif
                    @if ($goals->isNotEmpty())
                        <a href="#solo" class="inline-flex min-h-12 items-center justify-center rounded-full px-5 text-[15px] font-semibold text-ink/80 hover:text-ink">Go solo against the line</a>
                    @endif
                @endif
            </div>
        </section>

        @if ($state === 'no-league')
            <p class="flex items-center gap-2 rounded-xl bg-mint/60 px-3 py-2 text-[13px] text-ink/80" data-tip>
                <x-icon name="help" class="size-4 shrink-0 text-club" />
                <span><span class="font-semibold text-club">Tip:</span> tap the ? at the top any time for how leagues and scoring work, or to take the tour.</span>
            </p>
        @endif

        {{-- Their own week, against the line: the same live band as a matchup, just one side. --}}
        @if ($goals->isNotEmpty())
            <section id="solo" class="scroll-mt-24" aria-labelledby="solo-title">
                <h2 id="solo-title" class="eyebrow mb-1.5 text-club">This week against the line</h2>
                <div class="space-y-2">
                    @foreach ($goals as $goal)
                        @php($week = $goal->currentWeek())
                        <a href="{{ route('goals.show', $goal) }}" class="block rounded-xl border border-line bg-white px-3 py-2.5 hover:border-club/40">
                            <span class="flex items-center justify-between gap-2">
                                <span class="truncate text-[15px] font-bold text-club">{{ $goal->title }}</span>
                                <span class="shrink-0 text-xs font-semibold text-ink tabular-nums">{{ $week?->creditedPercent() ?? 0 }}%</span>
                            </span>
                            @if ($week)
                                <x-band-track :week="$week" compact />
                            @else
                                <span class="mt-1 block text-xs text-ink/70">Your first GoalWeek starts soon.</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    <x-start-sheet />
</x-layouts.app>
