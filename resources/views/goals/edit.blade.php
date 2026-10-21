<x-layouts.app title="Edit goal · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <p class="eyebrow text-mint">Edit goal</p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">{{ $goal->title }}</h1>
        </div>
    </x-slot:hero>

    <div class="mx-auto max-w-2xl rounded-2xl border border-line bg-white p-6 sm:p-10">
        <x-goal-form :goal="$goal" :action="route('goals.update', $goal)" method="PUT" submit="Save changes" />
    </div>

    <div class="mx-auto mt-6 flex max-w-2xl items-center justify-between gap-4 rounded-2xl border border-line px-6 py-4">
        <p class="text-sm text-ink/60">No longer chasing this one?</p>
        <a href="{{ route('goals.leave', $goal) }}" class="text-sm font-semibold text-red-700 hover:underline">{{ $goal->inTrial() ? 'Remove goal' : 'Retire goal' }}</a>
    </div>
</x-layouts.app>
