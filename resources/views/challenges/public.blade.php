{{-- Every public Challenge you can join, with search and filters (the Challenges tab's "See all"). --}}
<x-layouts.app title="Public Challenges · GoalLine">
    <x-slot:hero>
        <x-page-header title="Public Challenges" :back="route('leagues.index', ['tab' => 'challenges'])" back-label="Challenges" subtitle="Open to anyone. You'll see the rules before you join." />
    </x-slot:hero>

    <div class="space-y-3" data-first-content>
        @include('partials.public-filters', [
            'action' => route('challenges.browse'), 'kindLabel' => 'Type',
            'kinds' => collect(App\Models\Challenge::TYPES)->map(fn ($type) => $type['label'])->all(),
            'durations' => App\Services\PublicListings::CHALLENGE_DURATIONS,
        ])
        @if ($challenges->isEmpty())
            <div class="rounded-2xl border border-gold/60 bg-white px-4 py-5 text-center">
                <p class="text-[15px] font-semibold text-ink">No public Challenges match.</p>
                <p class="mt-1 text-[13px] text-ink/75">Try fewer filters, or start one and make it public.</p>
                <a href="{{ route('challenges.create') }}" class="mt-3 inline-flex min-h-11 items-center rounded-full bg-gold px-4 text-[13px] font-semibold text-ink">Start a Challenge</a>
            </div>
        @else
            <ul class="grid gap-2 sm:grid-cols-2" data-public-challenges>
                @foreach ($challenges as $challenge)
                    <x-public-challenge-card :challenge="$challenge" :member="isset($memberOf[$challenge->id])" :example="$example" />
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.app>
