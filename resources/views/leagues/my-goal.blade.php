{{-- Set your goal for a league you're already in: the same goal step as joining. --}}
<x-layouts.app :title="'Your goal · '.$league->name">
    <x-slot:hero>
        <x-page-header title="Set your goal" :subtitle="$league->name.($league->hasGoal() ? ' · Goal: '.$league->goalSummary() : '')" :back="route('leagues.show', $league)" :back-label="$league->name" />
    </x-slot:hero>

    <div class="mx-auto max-w-2xl rounded-2xl border border-line bg-white p-4 sm:p-6" data-first-content>
        <p class="mb-4 text-sm text-ink/70">You're in {{ $league->name }}. Pick the goal you'll play: your GoalWeek results against your own plan are what score your Matchups.</p>
        @include('leagues.partials.join-goal-step')
    </div>
</x-layouts.app>
