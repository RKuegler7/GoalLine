<x-layouts.app title="Start a challenge · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <a href="{{ route('leagues.index') }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; GoalLeagues &amp; Challenges</a>
            <h1 class="mt-6 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">Start a challenge</h1>
            <p class="mt-3 max-w-xl text-ivory/85">A short sprint with friends: one thing, every day. Check in each day you do it and climb the leaderboard.</p>
        </div>
    </x-slot:hero>

    <form method="POST" action="{{ route('challenges.store') }}" class="mx-auto max-w-2xl space-y-6 rounded-2xl border border-line bg-white p-6 sm:p-10">
        @csrf

        <x-form.input name="title" label="Challenge" maxlength="80" placeholder="No sugar for 30 days" required autofocus />
        <x-form.textarea name="description" label="Rules or a rallying cry (optional)" maxlength="500"
            hint="For example: fruit is fine, dessert isn't. Check in each day you stay clean." />

        <div class="grid gap-6 sm:grid-cols-3">
            <div>
                <label for="starts_on" class="mb-1.5 block text-sm font-medium text-ink/80">Starts</label>
                <input id="starts_on" name="starts_on" type="date" required value="{{ old('starts_on', $today->toDateString()) }}"
                    min="{{ $today->toDateString() }}" max="{{ $today->copy()->addDays(60)->toDateString() }}"
                    class="block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                @error('starts_on')
                    <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="length" class="mb-1.5 block text-sm font-medium text-ink/80">Length</label>
                <select id="length" name="length" required class="block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                    @foreach (App\Models\Challenge::LENGTHS as $days)
                        <option value="{{ $days }}" @selected((int) old('length', 30) === $days)>{{ $days }} days</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="target_days" class="mb-1.5 block text-sm font-medium text-ink/80">Days to finish</label>
                <input id="target_days" name="target_days" type="number" min="1" max="30" required value="{{ old('target_days', 30) }}"
                    class="block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                @error('target_days')
                    <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
        </div>
        <p class="-mt-3 text-sm text-ink/55">Everyone who checks in on that many days finishes the challenge and earns the Challenge Finisher badge. Use fewer than the length for things like &ldquo;run 20 days this month&rdquo;.</p>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
            <a href="{{ route('leagues.index') }}" class="rounded-lg px-4 py-3 text-center font-semibold text-ink/70 transition hover:text-ink">Cancel</a>
            <x-form.button class="sm:w-auto sm:px-8">Start the challenge</x-form.button>
        </div>
    </form>
</x-layouts.app>
