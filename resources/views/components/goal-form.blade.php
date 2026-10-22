@props(['goal' => null, 'action', 'method' => 'POST', 'submit'])

@php
    $selectedCategory = old('category', $goal?->category?->value);
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-7">
    @csrf
    @method($method)

    <x-form.input
        name="title"
        label="What do you want to accomplish?"
        :value="$goal?->title"
        placeholder="Pass the CPA exam"
        maxlength="120"
        required
        autofocus
    />

    <fieldset>
        <legend class="mb-2 block text-sm font-medium text-ink/80">What kind of goal is it?</legend>
        <div class="grid gap-2.5 sm:grid-cols-2">
            @foreach (App\GoalCategory::cases() as $category)
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-line bg-white px-4 py-3 transition hover:border-club/40 has-checked:border-club has-checked:bg-mint has-focus-visible:ring-4 has-focus-visible:ring-club/10">
                    <input
                        type="radio"
                        name="category"
                        value="{{ $category->value }}"
                        class="mt-1 accent-club"
                        @checked($selectedCategory === $category->value)
                        required
                    >
                    <span>
                        <span class="block font-medium text-ink">{{ $category->label() }}</span>
                        <span class="block text-sm text-ink/60">{{ $category->example() }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('category')
            <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </fieldset>

    <x-form.input
        name="target_date"
        label="When does it need to happen by?"
        type="date"
        :value="$goal?->target_date?->format('Y-m-d')"
        :min="AppClock::today()->addDay()->format('Y-m-d')"
        hint="Your roadmap will be built backward from this date."
        required
    />

    <x-form.textarea
        name="why"
        label="Why does it matter to you? (optional)"
        :value="$goal?->why"
        maxlength="1000"
        hint="We'll remind you of this on the hard days."
    />

    @unless ($goal)
        <x-material-upload-fields />
    @endunless

    <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-end">
        <a href="{{ $goal ? route('goals.show', $goal) : route('dashboard') }}" class="rounded-lg px-4 py-3 text-center font-semibold text-ink/70 transition hover:text-ink">Cancel</a>
        <x-form.button class="sm:w-auto sm:px-8">{{ $submit }}</x-form.button>
    </div>
</form>
