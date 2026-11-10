<x-layouts.app title="GoalLeagues & Challenges · GoalLine">
    <x-slot:hero>
        {{-- Title, then the big way to start: beside it on desktop, full width under it on phones. --}}
        <div class="mx-auto flex max-w-5xl flex-col gap-2.5 px-4 pt-1 pb-4 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-[20px] leading-tight font-bold tracking-tight text-ivory md:text-[24px]">GoalLeagues</h1>
            <x-start-buttons class="w-full sm:w-auto" />
        </div>
    </x-slot:hero>

    @php
        $tab = $errors->hasAny(['code', 'challenge_code']) ? 'invites' : $tab;
        $tabs = [
            ['label' => 'GoalLeagues', 'segment' => 'leagues', 'count' => $snapshots->count(), 'active' => $tab === 'leagues'],
            ['label' => 'Challenges', 'segment' => 'challenges', 'count' => $challenges->count(), 'active' => $tab === 'challenges'],
            ['label' => 'Invites', 'segment' => 'invites', 'count' => $invites->count() ?: null, 'active' => $tab === 'invites'],
        ];
    @endphp

    <div data-segmented data-first-content>
        <x-segmented :options="$tabs" label="Show" class="mb-3" />

        {{-- Your GoalLeagues: right at the top. --}}
        <section data-segment-panel="leagues" @if ($tab !== 'leagues') hidden @endif aria-label="Your GoalLeagues">
            @if ($snapshots->isEmpty())
                @include('partials.start-league-cta')
            @else
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($snapshots as $snapshot)
                        <x-league-card :snapshot="$snapshot" />
                    @endforeach
                    @include('partials.start-league-card')
                </div>
            @endif
        </section>

        {{-- Challenges: short sprints with a daily check-in. --}}
        <section data-segment-panel="challenges" @if ($tab !== 'challenges') hidden @endif aria-label="Challenges">
            @if ($challenges->isEmpty())
                <x-empty-state message="No challenges yet. Try “Run 20 days this month.”" action="Start a challenge" :href="route('challenges.create')" />
            @else
                <ul class="grid gap-2 sm:grid-cols-2">
                    @foreach ($challenges as $challenge)
                        @php
        $tab = $errors->hasAny(['code', 'challenge_code']) ? 'invites' : $tab;
                            $active = $challenge->isActive();
                            $percent = min(100, $challenge->my_days / max(1, $challenge->target_days) * 100);
                            $finished = $challenge->my_days >= $challenge->target_days;
                        @endphp
                        <li class="flex items-center gap-2 rounded-xl border border-line bg-white py-2 pr-2 pl-3">
                            <a href="{{ route('challenges.show', $challenge) }}" class="min-w-0 flex-1">
                                <span class="flex items-center justify-between gap-2">
                                    <span class="truncate text-[15px] font-bold text-club">{{ $challenge->title }}</span>
                                    <span class="shrink-0 text-xs font-semibold text-ink tabular-nums">{{ $challenge->my_days }}/{{ $challenge->target_days }}</span>
                                </span>
                                <x-progress-bar :percent="$percent" :tone="$finished ? 'gold' : 'grass'" class="mt-1.5" label="Your progress" />
                                <span class="mt-1 block truncate text-xs text-ink/70">
                                    @if (! $challenge->hasStarted())
                                        Starts {{ $challenge->starts_on->format('D, M j') }}
                                    @elseif ($challenge->isOver())
                                        Finished
                                    @else
                                        Day {{ $challenge->dayNumber() }} of {{ $challenge->lengthInDays() }}
                                    @endif
                                    &middot; {{ $challenge->members_count }} {{ Str::plural('member', $challenge->members_count) }}
                                </span>
                            </a>
                            @if ($active)
                                <form method="POST" action="{{ route('challenges.check-in', $challenge) }}" data-live class="shrink-0">
                                    @csrf
                                    <input type="hidden" name="return" value="list">
                                    <button type="submit" @class([
                                        'inline-flex min-h-11 items-center gap-1 rounded-lg px-2.5 text-xs font-semibold transition',
                                        'bg-grass/15 text-club' => $challenge->checked_today,
                                        'border border-club/30 text-club hover:bg-mint' => ! $challenge->checked_today,
                                    ]) title="{{ $challenge->checked_today ? 'Done today · tap to undo' : 'I did it today' }}">
                                        @if ($challenge->checked_today)
                                            <x-icon name="check" class="size-4" /> Done today
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
        </section>

        {{-- Invites, and joining with a code. --}}
        <section data-segment-panel="invites" @if ($tab !== 'invites') hidden @endif aria-label="Invites">
            @forelse ($invites as $invite)
                <a href="{{ $invite->url() }}" class="mb-2 flex items-center justify-between gap-3 rounded-xl border border-club/30 bg-white px-3 py-2.5 hover:bg-mint/40">
                    <span class="min-w-0">
                        <span class="block truncate text-[15px] font-bold text-club">{{ $invite->league->name }}</span>
                        <span class="block truncate text-xs text-ink/70">From {{ $invite->league->commissioner->name }}</span>
                    </span>
                    <span class="shrink-0 text-[13px] font-semibold text-club">Open <span aria-hidden="true">&rarr;</span></span>
                </a>
            @empty
                <p class="mb-2 text-[13px] text-ink/70">No invites waiting. Have a code? Join below.</p>
            @endforelse

            <div id="join" class="grid scroll-mt-20 gap-2 sm:grid-cols-2">
                <form method="POST" action="{{ route('leagues.find') }}" class="rounded-xl border border-line bg-white px-3 py-2.5">
                    @csrf
                    <label for="code" class="text-xs font-semibold text-ink/80">GoalLeague invite code</label>
                    <div class="mt-1 flex gap-2">
                        <input id="code" name="code" value="{{ old('code') }}" required placeholder="K7QX-M4PA" autocomplete="off" autocapitalize="characters"
                            class="min-w-0 flex-1 rounded-lg border border-line bg-white px-3 py-2 font-mono text-sm tracking-widest uppercase outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                        <button type="submit" class="min-h-10 rounded-lg bg-club px-4 text-sm font-semibold text-ivory hover:bg-club-dark">Find</button>
                    </div>
                    @error('code')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </form>
                <form method="POST" action="{{ route('challenges.find') }}" class="rounded-xl border border-line bg-white px-3 py-2.5">
                    @csrf
                    <label for="challenge_code" class="text-xs font-semibold text-ink/80">Challenge code</label>
                    <div class="mt-1 flex gap-2">
                        <input id="challenge_code" name="code" required placeholder="Have a challenge code?" autocomplete="off" autocapitalize="characters"
                            class="min-w-0 flex-1 rounded-lg border border-line bg-white px-3 py-2 font-mono text-sm tracking-widest uppercase outline-none placeholder:font-sans placeholder:tracking-normal placeholder:normal-case focus:border-club focus:ring-4 focus:ring-club/10">
                        <button type="submit" class="min-h-10 rounded-lg border border-club/30 px-4 text-sm font-semibold text-club hover:border-club">Join</button>
                    </div>
                    @error('challenge_code')
                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </form>
            </div>
        </section>
    </div>
</x-layouts.app>
