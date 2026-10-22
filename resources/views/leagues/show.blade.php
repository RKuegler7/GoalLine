@php
    $status = $league->status();
    $week = $league->currentWeek();
    $standings = $table->standings();
    $settledWeeks = $league->results->pluck('week')->unique()->sortDesc()->values();
@endphp

<x-layouts.app :title="$league->name.' · GoalLine'">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <a href="{{ route('leagues.index') }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; GoalLeagues</a>
            <p class="eyebrow mt-6 text-mint">
                @if ($status === App\LeagueStatus::Forming)
                    Forming &middot; kicks off {{ $league->starts_on->format('l, M j') }}
                @elseif ($status === App\LeagueStatus::Active)
                    In season &middot; week {{ $week }} of {{ $league->weeksCount() }}
                @else
                    Season over
                @endif
            </p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">{{ $league->name }}</h1>
            @if ($league->description)
                <p class="mt-3 max-w-xl text-ivory/85">{{ $league->description }}</p>
            @endif
            <p class="mt-3 text-sm text-ivory/70">
                {{ $league->members->count() }} {{ Str::plural('member', $league->members->count()) }} &middot;
                {{ $league->starts_on->format('M j') }} to {{ $league->ends_on->format('M j, Y') }} &middot;
                Commissioner: {{ $league->commissioner->name }}
            </p>
        </div>
    </x-slot:hero>

    @if ($status === App\LeagueStatus::Forming)
        {{-- Invite. --}}
        <section class="rounded-2xl border border-line bg-white px-6 py-5">
            <h2 class="eyebrow text-club">Invite your people</h2>
            <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-center">
                <div>
                    <p class="text-sm text-ink/60">Invite code</p>
                    <p class="font-mono text-3xl font-semibold tracking-[0.2em] text-club">{{ $league->invite_code }}</p>
                </div>
                <div class="min-w-0 flex-1 sm:border-l sm:border-line sm:pl-4">
                    <label for="invite-link" class="text-sm text-ink/60">Or share this link</label>
                    <input id="invite-link" readonly value="{{ route('leagues.join', $league->invite_code) }}" onclick="this.select()"
                        class="mt-1 block w-full rounded-lg border border-line bg-ivory px-3 py-2 text-sm text-ink/80 outline-none focus:border-club">
                </div>
            </div>
            <p class="mt-3 text-sm text-ink/55">
                People can join until kickoff. {{ App\Models\League::RECOMMENDED_MEMBERS }} or fewer works best; up to {{ App\Models\League::MAX_MEMBERS }} can join.
            </p>
        </section>
    @endif

    @if ($week)
        {{-- This week's GoalGroups, scored live. --}}
        <section class="mt-6">
            <div class="flex items-end justify-between gap-4">
                <h2 class="eyebrow text-club">Week {{ $week }} GoalGroups</h2>
                <span class="text-sm text-ink/50">{{ $league->weekStartsOn($week)->format('M j') }}&ndash;{{ $league->weekEndsOn($week)->format('M j') }} &middot; live</span>
            </div>
            <ul class="mt-3 space-y-3">
                @foreach (collect($table->matchups($week))->sortByDesc(fn ($m) => $m['home']->is($me) || $m['away']?->is($me)) as $matchup)
                    @php
                        $mine = $matchup['home']->is($me) || $matchup['away']?->is($me);
                    @endphp
                    <li @class(['rounded-2xl border bg-white px-5 py-4', 'border-club ring-4 ring-mint' => $mine, 'border-line' => ! $mine])>
                        <div class="flex items-center gap-3">
                            <x-matchup-side :member="$matchup['home']" :result="$matchup['homeResult']" :outcome="$matchup['homeOutcome']" :final="false" />
                            <span class="eyebrow shrink-0 text-[0.65rem] text-ink/40">vs</span>
                            @if ($matchup['away'])
                                <x-matchup-side :member="$matchup['away']" :result="$matchup['awayResult']" :outcome="$matchup['awayOutcome']" :final="false" align="right" />
                            @else
                                <div class="flex-1 text-right">
                                    <p class="font-medium text-ink">League median</p>
                                    <p class="mt-1 text-xs text-ink/55">
                                        {{ $matchup['median'] === null ? 'No one else yet' : round($matchup['median'], 1).' pts this week' }}
                                    </p>
                                </div>
                            @endif
                        </div>
                        @php
                            $standing = match ($matchup['homeOutcome']) {
                                App\MatchupOutcome::Win => 'leads',
                                App\MatchupOutcome::Tie => 'is level with',
                                App\MatchupOutcome::Loss => 'trails',
                            };
                        @endphp
                        <p class="mt-3 border-t border-line pt-2 text-xs text-ink/55">
                            So far, {{ $matchup['home']->user->name }} {{ $standing }} {{ $matchup['away']?->user->name ?? 'the league median' }}.
                            Final a day after the week ends.
                        </p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Standings. --}}
    <section class="mt-8">
        <h2 class="eyebrow text-club">Standings</h2>
        <div class="mt-3 overflow-x-auto rounded-2xl border border-line bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-line text-xs tracking-wide text-ink/50 uppercase">
                    <tr>
                        <th class="px-4 py-3 font-semibold">#</th>
                        <th class="px-2 py-3 font-semibold">Member</th>
                        <th class="px-2 py-3 text-center font-semibold" title="GoalWeeks played">P</th>
                        <th class="px-2 py-3 text-center font-semibold">W</th>
                        <th class="px-2 py-3 text-center font-semibold">T</th>
                        <th class="px-2 py-3 text-center font-semibold">L</th>
                        <th class="hidden px-2 py-3 text-center font-semibold sm:table-cell" title="GoalWeek result points">Result</th>
                        <th class="hidden px-2 py-3 text-center font-semibold sm:table-cell" title="Matchup points">Match</th>
                        <th class="px-4 py-3 text-right font-semibold">Pts</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($standings as $index => $row)
                        <tr @class(['bg-mint/40' => $row['member']->is($me)])>
                            <td class="px-4 py-3 font-semibold text-ink/60 tabular-nums">{{ $index + 1 }}</td>
                            <td class="px-2 py-3">
                                <div class="flex items-center gap-2.5">
                                    <x-avatar :user="$row['member']->user" class="size-7 text-[0.65rem]" />
                                    <div class="min-w-0">
                                        <a href="{{ $row['member']->user->profileUrl() }}" class="block truncate font-medium text-ink hover:text-club hover:underline">{{ $row['member']->user->name }}</a>
                                        <p class="truncate text-xs text-ink/50">{{ $row['member']->goal?->title ?? 'No goal selected' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-2 py-3 text-center tabular-nums">{{ $row['played'] }}</td>
                            <td class="px-2 py-3 text-center tabular-nums">{{ $row['wins'] }}</td>
                            <td class="px-2 py-3 text-center tabular-nums">{{ $row['ties'] }}</td>
                            <td class="px-2 py-3 text-center tabular-nums">{{ $row['losses'] }}</td>
                            <td class="hidden px-2 py-3 text-center tabular-nums sm:table-cell">{{ $row['resultPoints'] }}</td>
                            <td class="hidden px-2 py-3 text-center tabular-nums sm:table-cell">{{ $row['matchupPoints'] }}</td>
                            <td class="px-4 py-3 text-right font-serif text-lg font-medium text-club tabular-nums">{{ $row['total'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-ink/50">
            Points = GoalWeek result (0&ndash;3) + matchup (win 3, tie 1, loss 0), up to 6 a week. Ties in the table go to result points, then wins.
        </p>
    </section>

    {{-- Past weeks. --}}
    @if ($settledWeeks->isNotEmpty())
        <section class="mt-8">
            <h2 class="eyebrow text-club">Results</h2>
            <div class="mt-3 space-y-2">
                @foreach ($settledWeeks as $pastWeek)
                    @php
                        $rows = $league->results->where('week', $pastWeek)->sortByDesc(fn ($r) => $r->result_points + $r->matchup_points);
                    @endphp
                    <details class="rounded-2xl border border-line bg-white px-5 py-3" @if ($loop->first) open @endif>
                        <summary class="cursor-pointer font-medium text-club">
                            Week {{ $pastWeek }} <span class="font-normal text-ink/50">&middot; {{ $league->weekStartsOn($pastWeek)->format('M j') }}&ndash;{{ $league->weekEndsOn($pastWeek)->format('M j') }}</span>
                        </summary>
                        <ul class="mt-2 divide-y divide-line">
                            @foreach ($rows as $row)
                                @php
                                    $member = $league->members->firstWhere('user_id', $row->user_id);
                                @endphp
                                <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                                    <span class="font-medium text-ink">{{ $member?->user->name ?? 'Former member' }}</span>
                                    <span class="flex items-center gap-2">
                                        <x-goalweek-result :result="$row->result" />
                                        <span class="text-ink/50">vs {{ $row->vs_median ? 'league median' : ($league->members->firstWhere('user_id', $row->opponent_id)?->user->name ?? 'former member') }}</span>
                                        <span class="font-bold text-ink">{{ $row->outcome->letter() }}</span>
                                        <span class="text-ink/60 tabular-nums">+{{ $row->result_points + $row->matchup_points }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Your league goal. --}}
    <section class="mt-8 rounded-2xl border border-line bg-white px-6 py-5">
        <h2 class="eyebrow text-club">Your league goal</h2>
        @if ($me->goal)
            <p class="mt-2 text-ink">
                <a href="{{ route('goals.show', $me->goal) }}" class="font-serif text-lg font-medium text-club hover:underline">{{ $me->goal->title }}</a>
                <span class="text-sm text-ink/55">&middot; {{ $me->goal->status->value === 'active' ? 'in progress' : $me->goal->status->value }}</span>
            </p>
        @else
            <p class="mt-2 text-ink/70">You don't have a goal in this league right now, so your GoalWeeks count as short of the line.</p>
        @endif
        @if ($myGoals->isNotEmpty())
            <form method="POST" action="{{ route('leagues.membership.update', $league) }}" class="mt-3 flex flex-col gap-2 sm:flex-row">
                @csrf
                @method('PUT')
                <label for="league-goal" class="sr-only">Switch league goal</label>
                <select id="league-goal" name="goal_id" class="min-w-0 flex-1 rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none focus:border-club">
                    @foreach ($myGoals as $goal)
                        <option value="{{ $goal->id }}" @selected($me->goal_id === $goal->id)>{{ $goal->title }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg px-4 py-2 text-sm font-semibold text-club hover:bg-mint">Switch goal</button>
            </form>
            <p class="mt-1.5 text-xs text-ink/50">Switching affects GoalWeeks that aren't final yet. Settled results stay as they are.</p>
        @endif
    </section>

    {{-- League chat. Works as plain forms; app.js adds live updates and sending without a page reload. --}}
    <section id="chat" class="mt-6 scroll-mt-6 rounded-2xl border border-line bg-white"
        data-league-chat
        data-poll-url="{{ route('leagues.messages.index', $league) }}"
        data-last-id="{{ $messages->last()?->id ?? 0 }}">
        <div class="flex items-center justify-between border-b border-line px-6 py-4">
            <h2 class="eyebrow text-club">League chat</h2>
            <span class="flex items-center gap-1.5 text-xs text-ink/50"><span class="size-1.5 animate-pulse rounded-full bg-grass" aria-hidden="true"></span>Live</span>
        </div>

        <ol data-chat-list class="max-h-[28rem] overflow-y-auto px-6 py-2" aria-live="polite" aria-label="Messages">
            @if ($messages->isEmpty())
                <li data-chat-empty class="py-8 text-center text-ink/55">No messages yet. Say hi to your GoalLeague.</li>
            @endif
            @include('leagues.partials.messages', ['messages' => $messages])
        </ol>

        <form method="POST" action="{{ route('leagues.messages.store', $league) }}" data-chat-form class="flex items-end gap-2 border-t border-line px-4 py-3">
            @csrf
            <label for="chat-body" class="sr-only">Message</label>
            <textarea id="chat-body" name="body" rows="1" maxlength="{{ App\Models\LeagueMessage::MAX_LENGTH }}" required
                placeholder="Cheer someone on, call your shot, or talk trash..."
                class="max-h-40 min-h-[2.75rem] flex-1 resize-y rounded-xl border border-line bg-ivory/60 px-3.5 py-2.5 text-ink outline-none placeholder:text-ink/40 focus:border-club focus:bg-white focus:ring-4 focus:ring-club/10"></textarea>
            <button type="submit" class="rounded-xl bg-club px-4 py-2.5 font-semibold text-ivory hover:bg-club-dark">Send</button>
        </form>
        @error('body')
            <p class="px-6 pb-3 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </section>

    @can('leave', $league)
        <form method="POST" action="{{ route('leagues.membership.destroy', $league) }}" class="mt-10 border-t border-line pt-6 text-right"
            onsubmit="return confirm('Leave this GoalLeague? You can rejoin with the code before kickoff.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm font-semibold text-ink/60 hover:text-red-700">Leave GoalLeague</button>
        </form>
    @endcan
    @can('delete', $league)
        <form method="POST" action="{{ route('leagues.destroy', $league) }}" class="mt-10 border-t border-line pt-6 text-right"
            onsubmit="return confirm('Call off this GoalLeague? Everyone will be removed. This cannot be undone.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm font-semibold text-ink/60 hover:text-red-700">Call off GoalLeague</button>
        </form>
    @endcan
</x-layouts.app>
