<x-layouts.app title="Start a league · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <a href="{{ route('leagues.index') }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; Leagues</a>
            <h1 class="mt-6 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">Start a league</h1>
            <p class="mt-3 max-w-xl text-ivory/85">Everyone joins before kickoff and plays the whole season together.</p>
        </div>
    </x-slot:hero>

    @if ($goals->isEmpty())
        <div class="mx-auto max-w-2xl rounded-2xl border border-line bg-white p-8 text-center">
            <p class="font-serif text-xl text-club">You'll need a goal to play.</p>
            <p class="mt-2 text-ink/70">Each member brings one goal into the league, and its GoalWeek results score your matchups.</p>
            <a href="{{ route('goals.create') }}" class="mt-5 inline-block rounded-full bg-club px-6 py-2.5 font-semibold text-ivory hover:bg-club-dark">Set a goal first</a>
        </div>
    @else
        <form method="POST" action="{{ route('leagues.store') }}" class="mx-auto max-w-2xl space-y-6 rounded-2xl border border-line bg-white p-6 sm:p-10">
            @csrf

            <x-form.input name="name" label="League name" maxlength="80" placeholder="FAR Exam Crew" required autofocus />
            <x-form.textarea name="description" label="What's this league about? (optional)" maxlength="280"
                hint="For example: everyone sitting for FAR on December 15." />

            <div>
                <label for="goal_id" class="mb-1.5 block text-sm font-medium text-ink/80">Your goal in this league</label>
                <select id="goal_id" name="goal_id" required class="block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                    @foreach ($goals as $goal)
                        <option value="{{ $goal->id }}" @selected(old('goal_id') == $goal->id)>{{ $goal->title }} (by {{ $goal->target_date->format('M j, Y') }})</option>
                    @endforeach
                </select>
                @error('goal_id')
                    <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <label for="starts_on" class="mb-1.5 block text-sm font-medium text-ink/80">Kickoff</label>
                    <select id="starts_on" name="starts_on" required class="block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                        @foreach ($kickoffs as $monday)
                            <option value="{{ $monday->toDateString() }}" @selected(old('starts_on') === $monday->toDateString())>Monday, {{ $monday->format('M j') }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-sm text-ink/55">People can join until then.</p>
                    @error('starts_on')
                        <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <x-form.input name="ends_on" label="Season ends" type="date" :value="$goals->first()->target_date->format('Y-m-d')"
                    hint="Usually your shared deadline. We round to that week's Sunday." required />
            </div>

            <p class="rounded-xl bg-ivory px-4 py-3 text-sm text-ink/70">
                Leagues hold up to {{ App\Models\League::MAX_MEMBERS }} people; {{ App\Models\League::RECOMMENDED_MEMBERS }} or fewer works best, so everyone knows each other.
                Each GoalWeek you face a GoalGroup opponent in a rotating round-robin. With an odd number of members, one person plays the league median.
            </p>

            <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-end">
                <a href="{{ route('leagues.index') }}" class="rounded-lg px-4 py-3 text-center font-semibold text-ink/70 transition hover:text-ink">Cancel</a>
                <x-form.button class="sm:w-auto sm:px-8">Create league</x-form.button>
            </div>
        </form>
    @endif
</x-layouts.app>
