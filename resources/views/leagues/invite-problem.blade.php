{{-- An invite link or code that doesn't lead to a league: a calm page with somewhere to go, never a raw error. --}}
<x-dynamic-component :component="auth()->check() ? 'layouts.app' : 'layouts.field'" title="Invite · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-10">
            <p class="eyebrow text-mint">GoalLeague invite</p>
            <h1 class="mt-3 font-serif text-3xl font-bold tracking-tight text-ivory">Let's find your league</h1>
        </div>
    </x-slot:hero>

    <div @class(['mx-auto max-w-2xl rounded-2xl border border-line bg-white p-4 sm:p-6', 'my-10 mx-4 sm:mx-auto' => ! auth()->check()])>
        @include('leagues.partials.join-closed', ['reason' => $reason, 'league' => $league])
    </div>
</x-dynamic-component>
