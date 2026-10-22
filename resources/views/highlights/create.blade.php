<x-layouts.app title="Share a highlight · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <p class="eyebrow text-mint">{{ $draft['kind']->label() }}</p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">Share to Highlights</h1>
            <p class="mt-3 max-w-xl text-ivory/85">Your followers and GoalLeague mates will see it in their feed.</p>
        </div>
    </x-slot:hero>

    <form method="POST" action="{{ route('highlights.store') }}" enctype="multipart/form-data"
        class="mx-auto max-w-2xl overflow-hidden rounded-2xl border border-line bg-white">
        @csrf
        @if ($draft['kind'] === App\HighlightKind::GoalCompleted)
            <input type="hidden" name="goal" value="{{ $draft['goal']->id }}">
        @elseif ($draft['kind'] === App\HighlightKind::MilestoneHit)
            <input type="hidden" name="milestone" value="{{ $draft['week']->id }}">
        @else
            <input type="hidden" name="week" value="{{ $draft['week']->id }}">
        @endif

        <div class="relative overflow-hidden bg-club px-6 py-8">
            <x-field-backdrop class="absolute inset-0 size-full" />
            <div class="relative">
                <span @class([
                    'eyebrow inline-block rounded-full px-2.5 py-1 text-[0.65rem]',
                    'bg-gold text-ink' => $draft['kind'] === App\HighlightKind::GoalCompleted,
                    'bg-grass text-white' => $draft['kind'] === App\HighlightKind::GreatWeek,
                    'bg-mint text-club' => $draft['kind'] === App\HighlightKind::MilestoneHit,
                ])>{{ $draft['kind']->label() }}</span>
                <p class="mt-3 font-serif text-3xl font-medium text-balance text-ivory">{{ $draft['headline'] }}</p>
                <p class="mt-1 text-ivory/80">{{ $draft['detail'] }}</p>
            </div>
        </div>
        <x-goal-line />

        <div class="space-y-6 p-6 sm:p-8">
            <x-form.textarea name="caption" label="Say something (optional)" :value="$draft['caption']" maxlength="{{ App\Models\Highlight::MAX_CAPTION }}"
                hint="What it took, who helped, what's next." />

            @if ($draft['goalPhoto'])
                <label class="flex items-center gap-4 rounded-xl border border-line px-4 py-3 has-checked:border-club has-checked:bg-mint">
                    <input type="checkbox" name="use_goal_photo" value="1" checked class="accent-club">
                    <img src="{{ $draft['goalPhoto'] }}" alt="" class="size-14 rounded-lg object-cover">
                    <span class="text-sm text-ink/80">Use my photo from completing this goal</span>
                </label>
            @endif

            <div>
                <label for="photo" class="mb-1.5 block text-sm font-medium text-ink/80">{{ $draft['goalPhoto'] ? 'Or add a different photo' : 'Add a photo (optional)' }}</label>
                <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp"
                    class="block w-full text-sm text-ink/70 file:mr-4 file:rounded-lg file:border-0 file:bg-mint file:px-4 file:py-2 file:text-sm file:font-semibold file:text-club hover:file:bg-grass/20">
                <p class="mt-1.5 text-sm text-ink/55">JPG, PNG, or WebP, up to 5 MB.</p>
                @error('photo')
                    <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end">
                <a href="{{ url()->previous(route('highlights.index')) }}" class="rounded-lg px-5 py-3 text-center font-semibold text-ink/70 hover:text-ink">Not now</a>
                <x-form.button class="sm:w-auto sm:px-8">Share</x-form.button>
            </div>
        </div>
    </form>
</x-layouts.app>
