<x-layouts.welcome :step="2" title="What do you want to accomplish?" intro="Name the goal, date it, and upload any plan you're following. Your AI coach builds the roadmap.">
    @if ($existing)
        <div class="rounded-2xl border border-line bg-white p-6 text-center sm:p-8">
            <p class="eyebrow text-club">You're set</p>
            <p class="mt-2 font-serif text-2xl font-medium text-club">{{ $existing->title }}</p>
            <p class="mt-1 text-ink/60">Your roadmap is on its way.</p>
            <a href="{{ route('welcome.crew') }}" class="mt-6 inline-block rounded-full bg-club px-6 py-2.5 font-semibold text-ivory hover:bg-club-dark">Next: your crew</a>
        </div>
    @else
        <div class="rounded-2xl border border-line bg-white p-6 sm:p-8">
            <x-goal-form :action="route('goals.store')" submit="Set goal and continue" from="welcome"
                :cancel-url="route('welcome.crew')" cancel-label="Skip for now" />
        </div>
    @endif
</x-layouts.welcome>
