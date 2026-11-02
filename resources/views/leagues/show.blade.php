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
                    In season &middot; GoalWeek {{ $week }} of {{ $league->weeksCount() }}
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
            @if ($league->members->count() < App\Models\League::INVITE_PROMPT_BELOW && $league->isJoinable())
                <a href="#invite" class="mt-5 inline-flex items-center gap-2 rounded-full bg-gold px-5 py-2.5 font-semibold text-ink hover:brightness-105"><x-icon name="plus" class="size-4" /> Invite friends</a>
            @endif
        </div>
    </x-slot:hero>

    @if ($matchupWeek)
        {{-- This GoalWeek's matchup: yours at a glance, and the way into every matchup. --}}
        <a href="{{ route('leagues.matchups', [$league, $matchupWeek]) }}" class="mb-6 block overflow-hidden rounded-2xl border-2 border-club bg-white transition hover:shadow-md">
            <div class="flex items-center justify-between gap-3 bg-club px-5 py-3">
                <h2 class="font-serif text-xl font-medium text-ivory">GoalWeek {{ $matchupWeek }} Matchup</h2>
                <span class="eyebrow rounded-full bg-ivory/15 px-2 py-0.5 text-[0.6rem] text-mint">{{ $matchupFinal ? 'Final' : 'Live' }}</span>
            </div>
            @if ($myCard)
                <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 px-5 py-4">
                    @foreach ([$myCard['home'], $myCard['away']] as $index => $side)
                        @if ($index === 1)
                            <span class="font-serif text-sm text-ink/40">vs</span>
                        @endif
                        <div @class(['min-w-0', 'text-right' => $index === 1])>
                            <p class="truncate font-semibold text-ink">{{ $side ? $side['member']->user->name : 'League median' }}</p>
                            <p class="font-serif text-3xl font-medium text-club tabular-nums">
                                @if ($side)
                                    {{ $side['share'] === null ? '–' : round($side['share'] * 100).'%' }}
                                @else
                                    {{ $myCard['median'] === null ? '–' : round($myCard['median'], 1) }}
                                @endif
                            </p>
                            @if ($side && $side['result'])
                                <x-goalweek-result :result="$side['result']" :not-started="$side['notStarted']" />
                            @endif
                        </div>
                    @endforeach
                </div>
                <p class="border-t border-line px-5 py-2.5 text-sm text-ink/75">{{ $myCard['verdict'] }}</p>
            @endif
            <p class="flex items-center justify-between border-t border-line bg-mint/40 px-5 py-2.5 text-sm font-semibold text-club">
                <span>See all {{ $matchupCount }} {{ Str::plural('matchup', $matchupCount) }} &middot; swipe through them</span><span>&rarr;</span>
            </p>
        </a>
    @endif

    {{-- Your goal in this league: edit it, switch to another, or set a new one for this league. --}}
    <section class="mb-6 rounded-2xl border border-line bg-white px-5 py-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="eyebrow text-club">Your goal in this league</h2>
                @if ($me->goal)
                    <a href="{{ route('goals.show', $me->goal) }}" class="mt-1 block font-serif text-lg font-medium text-club hover:underline">{{ $me->goal->title }}</a>
                    <p class="text-xs text-ink/55">By {{ $me->goal->target_date->format('M j, Y') }} &middot; {{ $me->goal->isActive() ? 'in progress' : $me->goal->status->value }}</p>
                @else
                    <p class="mt-1 text-sm text-ink/70">You don't have a goal in this league right now, so your GoalWeeks count as short of the line.</p>
                @endif
            </div>
            @if ($me->goal?->isActive())
                <a href="{{ route('goals.edit', $me->goal) }}" class="shrink-0 rounded-full border border-club/40 px-4 py-1.5 text-sm font-semibold text-club hover:border-club">Edit goal</a>
            @endif
        </div>
        <details class="mt-3 border-t border-line pt-3" @if ($errors->has('goal_id')) open @endif>
            <summary class="cursor-pointer list-none text-sm font-semibold text-club hover:underline [&::-webkit-details-marker]:hidden">Play a different goal here</summary>
            @php
                $otherGoals = $myGoals->reject(fn ($goal) => $goal->id === $me->goal_id);
            @endphp
            @if ($otherGoals->isNotEmpty())
                <form method="POST" action="{{ route('leagues.membership.update', $league) }}" class="mt-3 flex flex-col gap-2 sm:flex-row">
                    @csrf
                    @method('PUT')
                    <label for="league-goal" class="sr-only">Switch to another goal</label>
                    <select id="league-goal" name="goal_id" class="min-w-0 flex-1 rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none focus:border-club">
                        @foreach ($otherGoals as $goal)
                            <option value="{{ $goal->id }}">{{ $goal->title }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-lg border border-line px-4 py-2 text-sm font-semibold text-club hover:border-club/40">Switch to this goal</button>
                </form>
            @endif
            <a href="{{ route('goals.create', ['league' => $league->id]) }}" class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-club px-4 py-2 text-sm font-semibold text-ivory hover:bg-club-dark">
                <x-icon name="plus" class="size-4" /> Set a new goal for this league
            </a>
            <p class="mt-2 text-xs text-ink/50">Switching affects GoalWeeks that aren't final yet. Settled results stay as they are.</p>
        </details>
    </section>

    <a href="{{ route('leagues.feed', $league) }}" class="mb-6 flex items-center justify-between gap-4 rounded-2xl border border-line bg-white px-5 py-4 transition hover:border-club/40">
        <span class="flex items-center gap-3">
            <span class="grid size-10 place-items-center rounded-full bg-mint text-club"><x-icon name="highlights" class="size-5" /></span>
            <span>
                <span class="block font-serif text-lg font-medium text-ink">League feed</span>
                <span class="block text-sm text-ink/65">The work behind everyone's check-ins. Cheer the process.</span>
            </span>
        </span>
        @if ($reportedPosts > 0)
            <span class="shrink-0 rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700">{{ $reportedPosts }} {{ Str::plural('report', $reportedPosts) }} to review</span>
        @else
            <span class="shrink-0 text-sm font-semibold text-club">Open &rarr;</span>
        @endif
    </a>

    @if ($status === App\LeagueStatus::Finished)
        <a href="{{ route('leagues.recap', $league) }}" class="mb-6 flex items-center justify-between gap-4 rounded-2xl border border-gold/60 bg-gold/15 px-5 py-4 transition hover:bg-gold/25">
            <span class="flex items-center gap-3">
                <x-badge :badge="App\Badge::Champion" class="size-10" />
                <span>
                    <span class="block font-serif text-lg font-medium text-ink">The season recap is in</span>
                    <span class="block text-sm text-ink/65">Final table, Champion, MVP, Comeback Player, and more.</span>
                </span>
            </span>
            <span class="shrink-0 text-sm font-semibold text-ink">See it &rarr;</span>
        </a>
    @endif

    @if ($status === App\LeagueStatus::Forming)
        @include('leagues.partials.invite')
    @endif


    {{-- Group tasks: proposed by anyone, in everyone's GoalWeek if two thirds vote yes within 24 hours. --}}
    <section id="group-tasks" class="mt-8 scroll-mt-6">
        <div class="flex items-end justify-between gap-4">
            <h2 class="eyebrow text-club">Group tasks</h2>
            @if ($groupTasksEnabled)
                <span class="text-xs text-ink/55">Needs {{ App\Models\GroupTask::requiredYes($league->members->count()) }} of {{ $league->members->count() }} yes votes within 24 hours</span>
            @endif
        </div>

        @if (! $groupTasksEnabled)
            <p class="mt-3 rounded-2xl border border-line bg-white px-5 py-4 text-sm text-ink/65">Group tasks open up once a second member joins. Then anyone can propose one, and if two thirds of the league say yes within 24 hours, it goes on everyone's GoalWeek.</p>
        @else
            @php
                $groupErrors = $errors->getBag('groupTask');
                $openWeeks = $groupTaskWeeks->filter(fn ($slots) => $slots > 0);
                $voting = $groupTasks->where('status', App\Models\GroupTask::VOTING);
                $decided = $groupTasks->where('status', '!=', App\Models\GroupTask::VOTING)->take(6);
            @endphp

            @foreach ($voting as $groupTask)
                @php
                    $myVote = $groupTask->voteOf(auth()->user());
                @endphp
                <article class="mt-3 rounded-2xl border border-gold/50 bg-white px-5 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="eyebrow text-[0.6rem] text-ink/50">Voting &middot; GoalWeek {{ $groupTask->week }} &middot; {{ $groupTask->sizeLabel() }}</p>
                            <h3 class="mt-1 font-serif text-lg font-medium text-ink">{{ $groupTask->title }}</h3>
                            @if ($groupTask->description)
                                <p class="text-sm text-ink/65">{{ $groupTask->description }}</p>
                            @endif
                            <p class="mt-1 text-xs text-ink/50">Proposed by {{ $groupTask->proposer?->name ?? 'a former member' }} &middot; voting closes {{ App\Clock::shortTime($groupTask->closes_at) }}</p>
                        </div>
                        <p class="shrink-0 text-sm tabular-nums"><span class="font-semibold text-grass">{{ $groupTask->yesCount() }} yes</span> &middot; <span class="text-ink/55">{{ $groupTask->noCount() }} no</span></p>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @foreach ([1 => 'Yes', 0 => 'No'] as $value => $label)
                            <form method="POST" action="{{ route('group-tasks.vote', $groupTask) }}">
                                @csrf
                                <input type="hidden" name="approve" value="{{ $value }}">
                                <button type="submit" aria-pressed="{{ $myVote === (bool) $value ? 'true' : 'false' }}" @class([
                                    'rounded-full px-4 py-1.5 text-sm font-semibold transition',
                                    'bg-club text-ivory' => $myVote === (bool) $value,
                                    'border border-line text-ink/70 hover:border-club/40' => $myVote !== (bool) $value,
                                ])>{{ $label }}</button>
                            </form>
                        @endforeach
                        <span class="text-xs text-ink/50">{{ $myVote === null ? "You haven't voted yet." : 'You can change your vote until it closes.' }}</span>
                    </div>
                </article>
            @endforeach

            @if ($openWeeks->isNotEmpty())
                <details class="mt-3 rounded-2xl border border-line bg-white px-5 py-4" @if ($groupErrors->any()) open @endif>
                    <summary class="cursor-pointer list-none font-semibold text-club [&::-webkit-details-marker]:hidden">+ Propose a group task</summary>
                    <form method="POST" action="{{ route('leagues.group-tasks.store', $league) }}" class="mt-3 space-y-3">
                        @csrf
                        <div>
                            <label for="group-title" class="text-sm font-semibold text-ink/80">Task</label>
                            <input id="group-title" name="title" maxlength="120" required value="{{ old('title') }}" placeholder="Read 20 minutes before bed"
                                class="mt-1 w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                        </div>
                        <div>
                            <label for="group-description" class="text-sm font-semibold text-ink/80">Details <span class="font-normal text-ink/45">(optional)</span></label>
                            <input id="group-description" name="description" maxlength="500" value="{{ old('description') }}"
                                class="mt-1 w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <div>
                                <label for="group-week" class="text-sm font-semibold text-ink/80">GoalWeek</label>
                                <select id="group-week" name="week" class="mt-1 block rounded-lg border border-line bg-white px-3 py-2 text-sm">
                                    @foreach ($openWeeks as $openWeek => $slots)
                                        <option value="{{ $openWeek }}" @selected((int) old('week') === $openWeek)>GoalWeek {{ $openWeek }} &middot; starts {{ $league->weekStartsOn($openWeek)->format('M j') }} &middot; {{ $slots }} {{ Str::plural('slot', $slots) }} left</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <fieldset>
                            <legend class="text-sm font-semibold text-ink/80">How big is it?</legend>
                            <p class="text-xs text-ink/55">
                                A regular task on your roadmap is worth 1 point.
                                @if ($typicalPoints)
                                    A typical GoalWeek in this league is about {{ $typicalPoints }} planned points.
                                @endif
                            </p>
                            <div class="mt-2 grid gap-2 sm:grid-cols-3">
                                @foreach (App\Models\GroupTask::SIZES as $points => [$size, $like])
                                    <label class="flex cursor-pointer flex-col rounded-xl border border-line bg-white px-3 py-2.5 transition hover:border-club/40 has-checked:border-club has-checked:bg-mint has-focus-visible:ring-4 has-focus-visible:ring-club/10">
                                        <span class="flex items-center justify-between gap-2">
                                            <span class="flex items-center gap-2 font-semibold text-ink">
                                                <input type="radio" name="points" value="{{ $points }}" class="accent-club" @checked((int) old('points', App\Models\GroupTask::DEFAULT_POINTS) === $points)>
                                                {{ $size }}
                                            </span>
                                            <span class="text-sm font-semibold text-club">{{ $points }} {{ Str::plural('pt', $points) }}</span>
                                        </span>
                                        <span class="mt-1 text-xs text-ink/60">{{ $like }}</span>
                                        @if ($typicalPoints)
                                            <span class="mt-1 text-xs text-ink/45">About {{ max(1, round($points / $typicalPoints * 100)) }}% of a typical week</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                            <p class="mt-2 text-xs text-ink/55">It's added to everyone's plan, so finishing it keeps you on pace and skipping it costs the same share for everyone.</p>
                        </fieldset>
                        <p class="text-xs text-ink/55">Your yes counts right away. If {{ App\Models\GroupTask::requiredYes($league->members->count()) }} of {{ $league->members->count() }} members say yes within 24 hours, it goes on everyone's GoalWeek for that week and counts in everyone's plan. Not enough yes votes means no task. Up to {{ App\Services\GroupTasks::MAX_PASSED_PER_WEEK }} a week.</p>
                        @if ($groupErrors->any())
                            <p class="text-sm text-red-700">{{ $groupErrors->first() }}</p>
                        @endif
                        <button type="submit" class="rounded-full bg-club px-5 py-2 text-sm font-semibold text-ivory hover:bg-club-dark">Propose to the league</button>
                    </form>
                </details>
            @elseif ($voting->isEmpty())
                <p class="mt-3 rounded-2xl border border-line bg-white px-5 py-4 text-sm text-ink/65">No weeks left with open group task slots this season.</p>
            @endif

            @if ($decided->isNotEmpty())
                <ul class="mt-3 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-white text-sm">
                    @foreach ($decided as $groupTask)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-2.5">
                            <span class="min-w-0">
                                <span class="font-medium text-ink">{{ $groupTask->title }}</span>
                                <span class="text-ink/50">&middot; GoalWeek {{ $groupTask->week }} &middot; {{ $groupTask->sizeLabel() }}</span>
                            </span>
                            @if ($groupTask->status === App\Models\GroupTask::PASSED)
                                <span class="rounded-full bg-mint px-2.5 py-0.5 text-xs font-semibold text-club">Passed &middot; on everyone's plan</span>
                            @else
                                <span class="rounded-full bg-ink/5 px-2.5 py-0.5 text-xs font-semibold text-ink/60" title="{{ $groupTask->outcome_note }}">Did not pass</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </section>

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
            Points = line points for your band (0&ndash;3) + match points (better band wins 2, same band draws 1), up to 5 a week. Ties in the table go to result points, then wins.
        </p>
    </section>

    {{-- Past weeks. --}}
    @if ($settledWeeks->isNotEmpty())
        <section class="mt-8">
            <div class="flex items-end justify-between gap-4">
                <h2 class="eyebrow text-club">Results</h2>
                <a href="{{ route('leagues.recap', $league) }}" class="text-sm font-semibold text-club hover:underline">Weekly recaps</a>
            </div>
            <div class="mt-3 space-y-2">
                @foreach ($settledWeeks as $pastWeek)
                    @php
                        $rows = $league->results->where('week', $pastWeek)->sortByDesc(fn ($r) => $r->result_points + $r->matchup_points);
                    @endphp
                    <details class="rounded-2xl border border-line bg-white px-5 py-3" @if ($loop->first) open @endif>
                        <summary class="cursor-pointer font-medium text-club">
                            GoalWeek {{ $pastWeek }} <span class="font-normal text-ink/50">&middot; {{ $league->weekStartsOn($pastWeek)->format('M j') }}&ndash;{{ $league->weekEndsOn($pastWeek)->format('M j') }}</span>
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
                        <a href="{{ route('leagues.matchups', [$league, $pastWeek]) }}" class="mt-1 inline-block text-sm font-semibold text-club hover:underline">GoalWeek {{ $pastWeek }} Matchup &rarr;</a>
                    </details>
                @endforeach
            </div>
        </section>
    @endif


    @unless ($status === App\LeagueStatus::Forming)
        <div class="mt-8">
            @include('leagues.partials.invite')
        </div>
    @endunless

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

        <form method="POST" action="{{ route('leagues.messages.store', $league) }}" data-chat-form class="relative flex items-end gap-2 border-t border-line px-4 py-3">
            @csrf
            <label for="chat-body" class="sr-only">Message</label>
            <textarea id="chat-body" name="body" rows="1" maxlength="{{ App\Models\LeagueMessage::MAX_LENGTH }}" required
                placeholder="Cheer someone on, call your shot, or talk trash. Type @ to mention someone."
                data-mention-input data-mentions="{{ $league->members->pluck('user')->filter()->reject(fn ($person) => $person->is(auth()->user()))->map(fn ($person) => ['u' => $person->username, 'n' => $person->name])->values()->toJson() }}"
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
