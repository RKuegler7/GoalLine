<x-layouts.app :title="($goal->inTrial() ? 'Remove' : 'Retire').' goal · GoalLine'">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <a href="{{ route('goals.show', $goal) }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; Back to goal</a>
            <p class="eyebrow mt-6 text-mint">{{ $goal->title }}</p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">
                {{ $goal->inTrial() ? 'Remove this goal?' : 'Retire this goal?' }}
            </h1>
        </div>
    </x-slot:hero>

    <div class="mx-auto max-w-2xl rounded-2xl border border-line bg-white p-6 sm:p-10">
        @if ($goal->inTrial())
            <p class="eyebrow text-club">Trial period</p>
            <p class="mt-3 text-lg text-ink">
                You set this goal on {{ $goal->created_at->format('F j') }}, so you're still in your {{ App\Models\Goal::TRIAL_DAYS }}-day trial
                until {{ $goal->lockedInAt()->format('F j') }}. You can remove it completely.
            </p>
            <p class="mt-3 text-ink/70">The goal and its roadmap will be deleted. This can't be undone.</p>

            <form method="POST" action="{{ route('goals.destroy', $goal) }}" class="mt-8 flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end">
                @csrf
                @method('DELETE')
                <a href="{{ route('goals.show', $goal) }}" class="rounded-lg px-5 py-3 text-center font-semibold text-club hover:bg-mint">Keep my goal</a>
                <button type="submit" class="rounded-lg bg-red-700 px-5 py-3 font-semibold text-white hover:bg-red-800">Yes, remove goal</button>
            </form>
        @else
            <p class="eyebrow text-club">Locked in</p>
            <p class="mt-3 text-lg text-ink">
                You locked in on {{ $goal->lockedInAt()->format('F j') }}. Retiring stops this goal and takes it off your clubhouse,
                but keeps its history. You can't undo it.
            </p>

            @if ($goal->roadmap_status === App\RoadmapStatus::Ready && $goal->replanFromPosition())
                <div class="mt-6 rounded-xl border border-grass/40 bg-mint px-5 py-4">
                    <p class="font-semibold text-club">Fallen behind, or life changed?</p>
                    <p class="mt-1 text-ink/75">
                        You don't have to quit. You can edit your tasks or re-plan the rest of your roadmap from here, and your coach will help you get back in line.
                    </p>
                    <a href="{{ route('goals.show', $goal) }}#goalweek-{{ $goal->replanFromPosition() }}" class="mt-2 inline-block text-sm font-semibold text-club hover:underline">Adjust my plan instead &rarr;</a>
                </div>
            @endif

            <form method="POST" action="{{ route('goals.retire', $goal) }}" class="mt-8 flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end">
                @csrf
                <a href="{{ route('goals.show', $goal) }}" class="rounded-lg px-5 py-3 text-center font-semibold text-club hover:bg-mint">Keep going</a>
                <button type="submit" class="rounded-lg bg-ink px-5 py-3 font-semibold text-white hover:bg-black">Yes, retire goal</button>
            </form>
        @endif
    </div>
</x-layouts.app>
