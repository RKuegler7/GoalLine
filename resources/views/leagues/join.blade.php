{{-- Joining a GoalLeague, from an invite link (works signed out) or a typed code. --}}
@php
    $viewer = auth()->user();
    $expired = $invite && ! $invite->isUsable();
    $chosenGoal = (int) old('goal_id', request()->integer('goal'));
    $inviter = $invite?->creator ?? $league->commissioner;
    $memberCount = $league->members->count();
    $summary = collect([
        $league->category?->label().($league->category ? ' goals' : null),
        $memberCount.' '.Str::plural('member', $memberCount),
        $league->weeksCount().'-week season',
    ])->filter()->implode(' · ');
@endphp

@push('head')
    @include('partials.share-meta', [
        'title' => "Join {$league->name} on GoalLine",
        'description' => Str::before($inviter->name, ' ')." invited you to a GoalLeague: {$summary}. Bring one real goal and play a friendly weekly matchup.",
        'url' => $invite ? $invite->url() : App\ShareLink::route('leagues.join', $league->invite_code),
    ])
@endpush

<x-dynamic-component :component="$viewer ? 'layouts.app' : 'layouts.field'" :title="'Join '.$league->name.' · GoalLine'">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <p class="eyebrow text-mint">{{ Str::before($inviter->name, ' ') }} invited you</p>
            <h1 class="mt-3 font-serif text-4xl font-bold tracking-tight text-ivory sm:text-5xl">{{ $league->name }}</h1>
            @if ($league->description)
                <p class="mt-3 max-w-xl text-ivory/85">{{ $league->description }}</p>
            @endif
            <p class="mt-3 text-sm text-ivory/85">
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
            <p class="font-serif text-xl text-club tracking-tight">This invite has expired or was turned off.</p>
            <p class="mt-2 text-ink/70">Ask {{ $league->commissioner->name }} for a fresh link.</p>
        @elseif (! $league->isJoinable())
            <p class="font-serif text-xl text-club tracking-tight">This GoalLeague can't take new members.</p>
            <p class="mt-2 text-ink/70">
                {{ $league->status() === App\LeagueStatus::Forming ? 'It is full.' : 'It has already kicked off, so the schedule is locked.' }}
                Ask the commissioner about the next season, or start your own.
            </p>
            @auth
                <a href="{{ route('leagues.create') }}" class="mt-5 inline-block rounded-full bg-club px-6 py-2.5 font-semibold text-ivory hover:bg-club-dark">Start a GoalLeague</a>
            @endauth
        @elseif (! $viewer)
            <p class="font-serif text-xl text-club tracking-tight">Sign in to join.</p>
            <p class="mt-2 text-ink/70">Each member brings one goal, and every GoalWeek your progress goes head-to-head with a league mate's. New to GoalLine? This invite gets you into the private beta.</p>
            <div class="mt-5 flex flex-wrap gap-3">
                <a href="{{ route('register') }}" class="rounded-full bg-club px-6 py-2.5 font-semibold text-ivory hover:bg-club-dark">Create an account</a>
                <a href="{{ route('login') }}" class="rounded-full border border-club/40 px-6 py-2.5 font-semibold text-club hover:border-club">Sign in</a>
            </div>
        @elseif ($goals->isEmpty())
            <p class="font-serif text-xl text-club tracking-tight">Set the goal you'll play.</p>
            <p class="mt-2 text-ink/70">Each member brings one goal into the league. Set yours here, then join with it.</p>
            @include('leagues.partials.new-goal-form')
        @else
            <form method="POST" action="{{ $joinAction }}" data-live class="space-y-6">
                @csrf
                <div>
                    <label for="goal_id" class="mb-1.5 block text-sm font-medium text-ink/80">Which goal will you play?</label>
                    <select id="goal_id" name="goal_id" required class="block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                        @foreach ($goals as $goal)
                            <option value="{{ $goal->id }}" @selected($chosenGoal === $goal->id)>{{ $goal->title }} (by {{ $goal->target_date->format('M j, Y') }})</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-sm text-ink/70">Its GoalWeek results score your matchups. You can switch goals later if one wraps up.</p>
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
            <details class="mt-4 rounded-xl border border-dashed border-line px-4 py-3" @if ($errors->has('title') || $errors->has('target_date')) open @endif>
                <summary class="cursor-pointer text-sm font-semibold text-club">Want to play a different goal? Set a new one for this league</summary>
                @include('leagues.partials.new-goal-form')
            </details>
        @endif
    </div>
</x-dynamic-component>
