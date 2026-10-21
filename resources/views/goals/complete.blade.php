<x-layouts.app title="Complete goal · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <a href="{{ route('goals.show', $goal) }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; Back to goal</a>
            <p class="eyebrow mt-6 text-mint">{{ $goal->title }}</p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">Did you cross the line?</h1>
        </div>
    </x-slot:hero>

    <form method="POST" action="{{ route('goals.complete.store', $goal) }}" enctype="multipart/form-data"
        class="mx-auto max-w-2xl overflow-hidden rounded-2xl border border-line bg-white">
        @csrf

        <div class="relative h-28 overflow-hidden bg-club">
            <x-field-backdrop class="absolute inset-0 size-full" />
        </div>
        <x-goal-line />

        <div class="space-y-6 p-6 sm:p-10">
            <p class="text-lg text-ink">
                Marking this goal complete puts it in your trophy case and closes its roadmap.
                @if ($goal->weeks->isNotEmpty())
                    You earned <span class="font-semibold text-club">{{ $goal->points() }} {{ Str::plural('point', $goal->points()) }}</span> across {{ $goal->weeks->filter->isStarted()->count() }} {{ Str::plural('GoalWeek', $goal->weeks->filter->isStarted()->count()) }} on the way.
                @endif
            </p>

            <x-form.textarea name="completion_note" label="How did it go? (optional)" maxlength="1000"
                hint="A few words for your future self: what it took, what you learned." />

            <div>
                <label for="completion_photo" class="mb-1.5 block text-sm font-medium text-ink/80">Add a photo (optional)</label>
                <input id="completion_photo" name="completion_photo" type="file" accept="image/jpeg,image/png,image/webp"
                    class="block w-full text-sm text-ink/70 file:mr-4 file:rounded-lg file:border-0 file:bg-mint file:px-4 file:py-2 file:text-sm file:font-semibold file:text-club hover:file:bg-grass/20">
                <p class="mt-1.5 text-sm text-ink/55">Your finish line photo, score report, or certificate. JPG, PNG, or WebP, up to 5 MB.</p>
                @error('completion_photo')
                    <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end">
                <a href="{{ route('goals.show', $goal) }}" class="rounded-lg px-5 py-3 text-center font-semibold text-club hover:bg-mint">Not yet</a>
                <x-form.button class="sm:w-auto sm:px-8">Yes, I did it</x-form.button>
            </div>
        </div>
    </form>
</x-layouts.app>
