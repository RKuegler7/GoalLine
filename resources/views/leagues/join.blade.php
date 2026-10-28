{{-- Joining a GoalLeague, from an invite link (works signed out) or a typed code. --}}
@php
    $viewer = auth()->user();
    $expired = $invite && ! $invite->isUsable();
    $chosenGoal = (int) old('goal_id', request()->integer('goal'));
@endphp

<x-dynamic-component :component="$viewer ? 'layouts.app' : 'layouts.field'" :title="'Join '.$league->name.' · GoalLine'">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <p class="eyebrow text-mint">You're invited</p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">{{ $league->name }}</h1>
            @if ($league->description)
                <p class="mt-3 max-w-xl text-ivory/85">{{ $league->description }}</p>
            @endif
            <p class="mt-3 text-sm text-ivory/70">
                @if ($league->category)
                    {{ $league->category->label() }} &middot;
                @endif
                Run by {{ $league->commissioner->name }} &middot; {{ $league->members->count() }} {{ Str::plural('member', $league->members->count()) }} &middot;
                {{ $league->weeksCount() }}-week season, {{ $league->starts_on->format('M j') }} to {{ $league->ends_on->format('M j, Y') }}
            </p>
        </div>
    </x-slot:hero>

    <div @class(['mx-auto max-w-2xl rounded-2xl border border-line bg-white p-6 sm:p-10', 'my-10 mx-4 sm:mx-auto' => ! $viewer])>
        @if ($expired)
            <p class="font-serif text-xl text-club">This invite has expired or was turned off.</p>
            <p class="mt-2 text-ink/70">Ask {{ $league->commissioner->name }} for a fresh link.</p>
        @elseif (! $league->isJoinable())
            <p class="font-serif text-xl text-club">This GoalLeague can't take new members.</p>
            <p class="mt-2 text-ink/70">
                {{ $league->status() === App\LeagueStatus::Forming ? 'It is full.' : 'It has already kicked off, so the schedule is locked.' }}
                Ask the commissioner about the next season, or start your own.
            </p>
            @auth
                <a href="{{ route('leagues.create') }}" class="mt-5 inline-block rounded-full bg-club px-6 py-2.5 font-semibold text-ivory hover:bg-club-dark">Start a GoalLeague</a>
            @endauth
        @elseif (! $viewer)
            <p class="font-serif text-xl text-club">Sign in to join.</p>
            <p class="mt-2 text-ink/70">Each member brings one goal, and every GoalWeek your progress goes head-to-head with a league mate's. New to GoalLine? This invite gets you into the private beta.</p>
            <div class="mt-5 flex flex-wrap gap-3">
                <a href="{{ route('register') }}" class="rounded-full bg-club px-6 py-2.5 font-semibold text-ivory hover:bg-club-dark">Create an account</a>
                <a href="{{ route('login') }}" class="rounded-full border border-club/40 px-6 py-2.5 font-semibold text-club hover:border-club">Sign in</a>
            </div>
        @elseif ($goals->isEmpty())
            <p class="font-serif text-xl text-club">Set the goal you'll play.</p>
            <p class="mt-2 text-ink/70">Each member brings one goal into the league. Set yours here, then join with it.</p>
            <form method="POST" action="{{ route('goals.store') }}" class="mt-5 space-y-4">
                @csrf
                <input type="hidden" name="from" value="league">
                <x-form.input name="title" label="Your goal" maxlength="120" placeholder="Pass FAR by December 15" required />
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="join-category" class="mb-1.5 block text-sm font-medium text-ink/80">Kind of goal</label>
                        <select id="join-category" name="category" required class="block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                            @foreach (App\GoalCategory::cases() as $category)
                                <option value="{{ $category->value }}" @selected(old('category', $league->category?->value) === $category->value)>{{ $category->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-form.input name="target_date" label="Done by" type="date" :value="$league->ends_on->format('Y-m-d')" required />
                </div>
                <x-form.button class="sm:w-auto sm:px-8">Set goal</x-form.button>
            </form>
        @else
            <form method="POST" action="{{ $joinAction }}" class="space-y-6">
                @csrf
                <div>
                    <label for="goal_id" class="mb-1.5 block text-sm font-medium text-ink/80">Which goal will you play?</label>
                    <select id="goal_id" name="goal_id" required class="block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                        @foreach ($goals as $goal)
                            <option value="{{ $goal->id }}" @selected($chosenGoal === $goal->id)>{{ $goal->title }} (by {{ $goal->target_date->format('M j, Y') }})</option>
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

                <div class="flex flex-col-reverse items-center justify-between gap-3 border-t border-line pt-6 sm:flex-row">
                    <x-form.button class="sm:w-auto sm:px-8">Join GoalLeague</x-form.button>
                </div>
            </form>
            <p class="mt-3 text-center text-sm text-ink/55">
                Want to play a different goal?
                <a href="{{ route('goals.create') }}" class="font-semibold text-club hover:underline">Set a new one</a>
            </p>
        @endif
    </div>
</x-dynamic-component>
