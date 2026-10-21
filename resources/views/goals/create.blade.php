<x-layouts.app title="Set a goal · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <p class="eyebrow text-mint">New goal</p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">Set your goal</h1>
            <p class="mt-3 max-w-xl text-ivory/85">Name it, date it, and tell us why. We'll take it from there.</p>
        </div>
    </x-slot:hero>

    <div class="mx-auto max-w-2xl rounded-2xl border border-line bg-white p-6 sm:p-10">
        <x-goal-form :action="route('goals.store')" submit="Set goal" />
    </div>
</x-layouts.app>
