<x-layouts.app :title="'Join '.$league->name.' · GoalLine'">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <p class="eyebrow text-mint">You're invited</p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">{{ $league->name }}</h1>
            @if ($league->description)
                <p class="mt-3 max-w-xl text-ivory/85">{{ $league->description }}</p>
            @endif
            <p class="mt-3 text-sm text-ivory/70">
                Run by {{ $league->commissioner->name }} &middot; {{ $league->members->count() }} {{ Str::plural('member', $league->members->count()) }} &middot;
                {{ $league->weeksCount() }}-week season, {{ $league->starts_on->format('M j') }} to {{ $league->ends_on->format('M j, Y') }}
            </p>
        </div>
    </x-slot:hero>

    <div class="mx-auto max-w-2xl rounded-2xl border border-line bg-white p-6 sm:p-10">
        @if (! $league->isJoinable())
            <p class="font-serif text-xl text-club">This league can't take new members.</p>
            <p class="mt-2 text-ink/70">
                {{ $league->status() === App\LeagueStatus::Forming ? 'It is full.' : 'It has already kicked off, so the schedule is locked.' }}
                Ask the commissioner about the next season, or start your own.
            </p>
            <a href="{{ route('leagues.create') }}" class="mt-5 inline-block rounded-full bg-club px-6 py-2.5 font-semibold text-ivory hover:bg-club-dark">Start a league</a>
        @elseif ($goals->isEmpty())
            <p class="font-serif text-xl text-club">Set a goal to play.</p>
            <p class="mt-2 text-ink/70">Each member brings one goal into the league. Set yours, then come back to this invite.</p>
            <a href="{{ route('goals.create') }}" class="mt-5 inline-block rounded-full bg-club px-6 py-2.5 font-semibold text-ivory hover:bg-club-dark">Set a goal</a>
        @else
            <form method="POST" action="{{ route('leagues.join.store', $league->invite_code) }}" class="space-y-6">
                @csrf
                <div>
                    <label for="goal_id" class="mb-1.5 block text-sm font-medium text-ink/80">Which goal will you play?</label>
                    <select id="goal_id" name="goal_id" required class="block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                        @foreach ($goals as $goal)
                            <option value="{{ $goal->id }}">{{ $goal->title }} (by {{ $goal->target_date->format('M j, Y') }})</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-sm text-ink/55">Its GoalWeek results score your matchups. You can switch goals later if one wraps up.</p>
                    @error('goal_id')
                        <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <ul class="flex flex-wrap gap-2" aria-label="Members">
                    @foreach ($league->members as $member)
                        <li class="flex items-center gap-2 rounded-full bg-ivory py-1 pr-3 pl-1 text-sm text-ink/80">
                            <x-avatar :user="$member->user" class="size-6 text-[0.6rem]" /> {{ $member->user->name }}
                        </li>
                    @endforeach
                </ul>

                <div class="flex justify-end border-t border-line pt-6">
                    <x-form.button class="sm:w-auto sm:px-8">Join league</x-form.button>
                </div>
            </form>
        @endif
    </div>
</x-layouts.app>
