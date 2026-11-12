{{-- Goal Day check-in: "Did you reach your goal?" Self-reported, so it never changes stars or standings. --}}
@php
    $chosen = old('outcome', $result?->outcome?->value ?? ($goal->isCompleted() || $early ? 'achieved' : null));
    $shared = old('shared', $result?->shared ?? true);
@endphp

<x-layouts.app title="Goal Day · GoalLine">
    <x-slot:hero>
        <x-page-header :title="$early ? 'I finished early' : 'Goal Day'" :eyebrow="$goal->title" :back="route('goals.show', $goal)" :back-label="$goal->title" />
    </x-slot:hero>

    <form method="POST" action="{{ route('goal-day.store', $goal) }}" enctype="multipart/form-data"
        class="mx-auto max-w-2xl overflow-hidden rounded-2xl border border-line bg-white" data-goal-day-form>
        @csrf

        <div class="relative h-20 overflow-hidden bg-club">
            <x-field-backdrop class="absolute inset-0 size-full" />
        </div>
        <x-goal-line />

        <div class="space-y-5 p-4 sm:p-6">
            <fieldset>
                <legend class="font-serif text-2xl font-bold text-ink">Did you reach your goal?</legend>
                <p class="mt-1 text-sm text-ink/75">
                    {{ $goal->target_result ? 'Your target: '.$goal->target_result.'.' : 'Your goal: '.$goal->title.'.' }}
                    This is just for you and your people. It never changes your stars or the table.
                </p>
                <div class="mt-3 grid gap-2 sm:grid-cols-3">
                    @foreach (App\GoalOutcome::cases() as $outcome)
                        @continue($early && $outcome !== App\GoalOutcome::Achieved)
                        <label class="gl-tap flex min-h-12 cursor-pointer items-center gap-2 rounded-xl border border-line px-3 py-2 font-semibold text-ink has-checked:border-club has-checked:bg-mint has-focus-visible:ring-4 has-focus-visible:ring-club/20" data-outcome="{{ $outcome->value }}">
                            <input type="radio" name="outcome" value="{{ $outcome->value }}" class="size-4 accent-club" @checked($chosen === $outcome->value) required>
                            {{ $outcome->choice() }}
                        </label>
                    @endforeach
                </div>
                @error('outcome')
                    <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </fieldset>

            <x-form.input name="actual_result" label="Your actual result (optional)" maxlength="80"
                :value="old('actual_result', $result?->actual_result)"
                :hint="$goal->target_result ? 'For example, next to your target of '.$goal->target_result.'.' : 'A time, a score, a number, or a few words.'" />

            <x-form.textarea name="hardest_part" label="What was the hardest part? (optional)" maxlength="280" :value="old('hardest_part', $result?->hardest_part)" />
            <x-form.textarea name="helped_most" label="What helped most? (optional)" maxlength="280" :value="old('helped_most', $result?->helped_most)" />

            <div>
                <label for="proof" class="mb-1.5 block text-sm font-medium text-ink/80">Add proof (optional)</label>
                <input id="proof" name="proof" type="file" accept="image/jpeg,image/png,image/webp"
                    class="block w-full text-sm text-ink/70 file:mr-4 file:rounded-lg file:border-0 file:bg-mint file:px-4 file:py-2 file:text-sm file:font-semibold file:text-club hover:file:bg-grass/20">
                <p class="mt-1.5 text-sm text-ink/70">
                    A finish photo, score report or certificate. Location and camera data are removed. Proof (or a league mate's confirm) makes an achieved goal <span class="font-semibold text-club">Verified</span>.
                    @if ($result?->proof_path)
                        You've already added proof; a new photo replaces it.
                    @endif
                </p>
                @error('proof')
                    <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-start justify-between gap-4 rounded-xl bg-ivory px-3 py-3 ring-1 ring-line ring-inset">
                <div>
                    <p class="font-semibold text-ink">{{ $league ? 'Share with my league' : 'Share with my people' }}</p>
                    <p class="text-sm text-ink/70">
                        {{ $league ? 'Your result goes to '.$league->name.'\'s Goal Day news, where league mates can cheer it. Turn off to keep it private.' : 'Off keeps it private. Either way it\'s in your History.' }}
                    </p>
                </div>
                <x-form.toggle name="shared" :checked="(bool) $shared" label="Share with my league" />
            </div>

            @if ($result)
                <p class="text-xs text-ink/70">You can change this until {{ $result->answered_at->copy()->addDays(App\Models\GoalResult::EDITABLE_DAYS)->format('M j') }}.</p>
            @endif

            <div class="flex flex-col-reverse gap-3 border-t border-line pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route('goals.show', $goal) }}" class="rounded-lg px-5 py-3 text-center font-semibold text-club hover:bg-mint">Not now</a>
                <x-form.button class="sm:w-auto sm:px-8">{{ $result ? 'Save changes' : 'See my season' }}</x-form.button>
            </div>
        </div>
    </form>
</x-layouts.app>
