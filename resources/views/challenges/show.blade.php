@php
    $mine = $standings->first(fn ($row) => $row['member']->user_id === $me->id);
    $checkedToday = $challenge->isCheckedIn($me, $today);
    $yesterday = $today->copy()->subDay();
    $canCatchUp = $challenge->isCheckableOn($yesterday) && ! $challenge->isCheckedIn($me, $yesterday);
    $percent = $challenge->target_days > 0 ? min(100, ($mine['days'] ?? 0) / $challenge->target_days * 100) : 0;
@endphp

<x-layouts.app :title="$challenge->title.' · GoalLine'">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <a href="{{ route('leagues.index') }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; GoalLeagues &amp; Challenges</a>
            @php
                $myDays = $challenge->checkIns->where('user_id', $me->id)->map(fn ($checkIn) => $checkIn->checked_on->toDateString())->flip();
            @endphp
            <p class="eyebrow mt-6 flex items-center gap-1.5 text-gold">
                <x-icon name="flag" class="size-4" />
                Challenge &middot;
                @if (! $challenge->hasStarted())
                    starts {{ $challenge->starts_on->format('l, M j') }}
                @elseif ($challenge->isOver())
                    finished {{ $challenge->ends_on->format('M j') }}
                @else
                    day {{ $challenge->dayNumber() }} of {{ $challenge->lengthInDays() }}
                @endif
            </p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-balance text-ivory sm:text-5xl">{{ $challenge->title }}</h1>
            @if ($challenge->description)
                <p class="mt-3 max-w-xl whitespace-pre-line text-ivory/85">{{ $challenge->description }}</p>
            @endif

            {{-- The challenge track: one square per day, gold where you checked in. --}}
            <ol class="mt-6 flex flex-wrap gap-1.5" aria-label="Your days in this challenge">
                @for ($day = $challenge->starts_on->copy(); $day->lte($challenge->ends_on); $day->addDay())
                    @php
                        $done = $myDays->has($day->toDateString());
                        $isToday = $day->isSameDay($today);
                    @endphp
                    <li @class([
                        'size-5 rounded-md sm:size-6',
                        'bg-gold' => $done,
                        'bg-ivory/15' => ! $done && $day->lte($today),
                        'bg-ivory/5 ring-1 ring-ivory/15 ring-inset' => ! $done && $day->gt($today),
                        'ring-2 ring-ivory ring-offset-2 ring-offset-club' => $isToday,
                    ]) title="{{ $day->format('D, M j') }}{{ $done ? ': checked in' : '' }}"><span class="sr-only">{{ $day->format('M j') }}{{ $done ? ', checked in' : '' }}</span></li>
                @endfor
            </ol>

            <p class="mt-4 text-sm text-ivory/70">
                {{ $challenge->starts_on->format('M j') }}&ndash;{{ $challenge->ends_on->format('M j') }} &middot;
                finish by checking in on {{ $challenge->target_days }} of {{ $challenge->lengthInDays() }} days &middot;
                {{ $challenge->members->count() }} {{ Str::plural('member', $challenge->members->count()) }}
            </p>
        </div>
    </x-slot:hero>

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="space-y-6">
            {{-- Today. --}}
            <section class="rounded-2xl border border-club/30 bg-white px-6 py-5">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="eyebrow text-club">Your progress</p>
                        <p class="mt-1 font-serif text-3xl text-ink tabular-nums">{{ $mine['days'] ?? 0 }}<span class="text-lg text-ink/45">/{{ $challenge->target_days }} days</span></p>
                        <p class="text-sm text-ink/60">
                            @if ($mine['finished'] ?? false)
                                Finished! Every extra day is a bonus.
                            @elseif ($mine)
                                {{ $challenge->target_days - $mine['days'] }} to go &middot; current run {{ $mine['run'] }} {{ Str::plural('day', $mine['run']) }}
                            @endif
                        </p>
                    </div>
                    @if ($challenge->isCheckableOn($today))
                        <form method="POST" action="{{ route('challenges.check-in', $challenge) }}">
                            @csrf
                            <button type="submit" @class([
                                'flex items-center gap-2 rounded-full px-6 py-3 font-semibold transition',
                                'bg-grass text-white' => $checkedToday,
                                'bg-club text-ivory hover:bg-club-dark' => ! $checkedToday,
                            ])>
                                @if ($checkedToday)
                                    <svg viewBox="0 0 16 16" class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7" /></svg>
                                    Done today
                                @else
                                    I did it today
                                @endif
                            </button>
                        </form>
                    @endif
                </div>
                <x-goal-progress class="mt-4" :percent="$percent" label="Challenge progress" />
                @if ($canCatchUp)
                    <form method="POST" action="{{ route('challenges.check-in', $challenge) }}" class="mt-3 text-sm text-ink/65">
                        @csrf
                        <input type="hidden" name="date" value="{{ $yesterday->toDateString() }}">
                        Did it yesterday but forgot to check in? <button type="submit" class="font-semibold text-club hover:underline">Check in for {{ $yesterday->format('l') }}</button>
                    </form>
                @endif
                @if (! $challenge->hasStarted())
                    <p class="mt-3 text-sm text-ink/60">Check-ins open {{ $challenge->starts_on->format('l, M j') }}.</p>
                @endif
            </section>

            {{-- Leaderboard. --}}
            <section>
                <h2 class="eyebrow text-club">Leaderboard</h2>
                <ol class="mt-3 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-white">
                    @foreach ($standings as $row)
                        @php
                            $isMe = $row['member']->user_id === $me->id;
                        @endphp
                        <li @class(['flex items-center gap-3 px-5 py-3', 'bg-mint/50' => $isMe])>
                            <span @class(['w-6 shrink-0 text-center font-serif text-lg tabular-nums', 'text-gold' => $row['rank'] === 1 && $row['days'] > 0, 'text-ink/50' => $row['rank'] !== 1 || $row['days'] === 0])>{{ $row['rank'] }}</span>
                            <x-avatar :user="$row['member']->user" class="size-9 text-xs" />
                            <span class="min-w-0 flex-1">
                                <a href="{{ $row['member']->user->profileUrl() }}" class="block truncate font-medium text-ink hover:text-club hover:underline">{{ $row['member']->user->name }}{{ $isMe ? ' (you)' : '' }}</a>
                                <span class="text-xs text-ink/55">run {{ $row['run'] }} {{ Str::plural('day', $row['run']) }}</span>
                            </span>
                            @if ($row['finished'])
                                <x-badge :badge="App\Badge::ChallengeFinisher" class="size-7" />
                            @endif
                            <span class="font-serif text-xl text-ink tabular-nums">{{ $row['days'] }}<span class="text-sm text-ink/45">/{{ $challenge->target_days }}</span></span>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>

        <aside class="space-y-6">
            @unless ($challenge->isOver())
                <section class="rounded-2xl border border-line bg-white px-5 py-4">
                    <h2 class="eyebrow text-club">Bring people in</h2>
                    <p class="mt-2 font-mono text-2xl font-semibold tracking-widest text-ink">{{ $challenge->invite_code }}</p>
                    <label for="challenge-link" class="mt-3 block text-sm text-ink/60">Or share this link</label>
                    <div class="mt-1 flex gap-2">
                        <input id="challenge-link" readonly value="{{ $challenge->joinUrl() }}" onclick="this.select()"
                            class="min-w-0 flex-1 rounded-lg border border-line bg-ivory px-3 py-2 text-xs text-ink/80 outline-none focus:border-club">
                        <button type="button" data-copy="challenge-link" class="shrink-0 rounded-lg border border-line px-3 py-2 text-sm font-semibold text-club hover:border-club/40">Copy</button>
                    </div>
                    <p class="mt-2 text-xs text-ink/50">Anyone can join until the last day.</p>
                </section>
            @endunless

            <section class="text-sm text-ink/60">
                <p>Started by {{ $challenge->creator->name }}.</p>
                @can('delete', $challenge)
                    <form method="POST" action="{{ route('challenges.destroy', $challenge) }}" onsubmit="return confirm('Call off this challenge for everyone?')" class="mt-2">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="font-semibold text-ink/60 hover:text-red-700">Call off the challenge</button>
                    </form>
                @elseif (! $challenge->isOver())
                    <form method="POST" action="{{ route('challenges.leave', $challenge) }}" onsubmit="return confirm('Leave this challenge? Your check-ins go with you.')" class="mt-2">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="font-semibold text-ink/60 hover:text-red-700">Leave the challenge</button>
                    </form>
                @endcan
            </section>
        </aside>
    </div>
</x-layouts.app>
