<x-layouts.app title="Leagues · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <p class="eyebrow text-mint">Leagues</p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">Your GoalGroups</h1>
            <p class="mt-3 max-w-xl text-ivory/85">Go head-to-head every GoalWeek with people chasing goals alongside you. The league is the mechanism, not the point.</p>
        </div>
    </x-slot:hero>

    <div class="grid gap-4 sm:grid-cols-2">
        <a href="{{ route('leagues.create') }}" class="group rounded-2xl border border-line bg-white px-6 py-5 transition hover:border-club/40">
            <p class="eyebrow text-club">Start a league</p>
            <p class="mt-2 font-serif text-xl font-medium text-club">Be the commissioner</p>
            <p class="mt-1 text-ink/65">Pick a kickoff Monday and a season end, then invite your people.</p>
            <span class="mt-3 inline-block text-sm font-semibold text-club group-hover:underline">Create a league &rarr;</span>
        </a>

        <form method="POST" action="{{ route('leagues.find') }}" class="rounded-2xl border border-line bg-white px-6 py-5">
            @csrf
            <p class="eyebrow text-club">Join a league</p>
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
        <h2 class="eyebrow text-club">Your leagues</h2>
        @if ($leagues->isEmpty())
            <div class="mt-3 overflow-hidden rounded-2xl border border-line bg-white">
                <div class="relative h-24 overflow-hidden bg-club">
                    <x-field-backdrop class="absolute inset-0 size-full" />
                </div>
                <x-goal-line />
                <p class="px-6 py-5 text-ink/70">You're not in a league yet. Leagues are optional: start one with friends, teammates, or classmates, or join with a code.</p>
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
