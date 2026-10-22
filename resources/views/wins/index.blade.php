<x-layouts.app title="Wins · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <p class="eyebrow text-mint">Wins</p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">Celebrate the crossings</h1>
            <p class="mt-3 max-w-xl text-ivory/85">Finished goals, smashed GoalWeeks, and big milestones from the people you're rooting for.</p>
        </div>
    </x-slot:hero>

    <section class="overflow-hidden rounded-2xl border border-line bg-white">
        <div class="relative h-36 overflow-hidden bg-club">
            <x-field-backdrop class="absolute inset-0 size-full" />
        </div>
        <x-goal-line />
        <div class="px-8 py-10 text-center">
            <p class="eyebrow text-club">Coming next</p>
            <h2 class="mt-3 font-serif text-2xl font-medium text-club">Your Wins feed is on its way</h2>
            <p class="mx-auto mt-2 max-w-md text-ink/70">
                Share a finished goal with a photo and a few words, cheer on friends outside your leagues, and keep a record of every line you've crossed.
            </p>
        </div>
    </section>
</x-layouts.app>
