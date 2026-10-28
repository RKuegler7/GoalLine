<x-layouts.app :title="'Play it with friends? · GoalLine'">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <p class="eyebrow text-mint">Goal set</p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">Play it with friends?</h1>
            <p class="mt-3 max-w-xl text-ivory/85">&ldquo;{{ $goal->title }}&rdquo; is in line. Goals stick when people are in it with you: each GoalWeek your progress goes head-to-head with a friend's.</p>
        </div>
    </x-slot:hero>

    <div class="mx-auto grid max-w-3xl gap-4 sm:grid-cols-3">
        <form method="POST" action="{{ route('goals.next.choose', $goal) }}" class="flex flex-col rounded-2xl border-2 border-club bg-white p-5">
            @csrf
            <input type="hidden" name="choice" value="create">
            <x-icon name="leagues" class="size-7 text-club" />
            <h2 class="mt-3 font-serif text-xl font-medium text-club">Create a league</h2>
            <p class="mt-1 flex-1 text-sm text-ink/65">We'll fill it in from this goal. Then invite friends with a link, a code, or their username.</p>
            <button type="submit" class="mt-4 rounded-full bg-club px-5 py-2.5 font-semibold text-ivory hover:bg-club-dark">Create a GoalLeague</button>
        </form>

        <form method="POST" action="{{ route('goals.next.choose', $goal) }}" class="flex flex-col rounded-2xl border border-line bg-white p-5">
            @csrf
            <input type="hidden" name="choice" value="join">
            <x-icon name="flag" class="size-7 text-club" />
            <h2 class="mt-3 font-serif text-xl font-medium text-club">Join a league</h2>
            <label for="next-code" class="mt-1 text-sm text-ink/65">Got an invite code?</label>
            <input id="next-code" name="code" required value="{{ old('code') }}" placeholder="K7QX-M4PA" autocomplete="off" autocapitalize="characters"
                class="mt-2 rounded-lg border border-line bg-white px-3 py-2 font-mono tracking-widest uppercase outline-none focus:border-club focus:ring-4 focus:ring-club/10">
            @error('code')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
            <span class="flex-1"></span>
            <button type="submit" class="mt-4 rounded-full border border-club/40 px-5 py-2.5 font-semibold text-club hover:border-club">Find the league</button>
        </form>

        <form method="POST" action="{{ route('goals.next.choose', $goal) }}" class="flex flex-col rounded-2xl border border-line bg-white p-5">
            @csrf
            <input type="hidden" name="choice" value="solo">
            <x-icon name="flame" class="size-7 text-ink/50" />
            <h2 class="mt-3 font-serif text-xl font-medium text-ink">Keep it solo</h2>
            <p class="mt-1 flex-1 text-sm text-ink/65">Just you against the line, every GoalWeek. You can start or join a league any time.</p>
            <button type="submit" class="mt-4 rounded-full px-5 py-2.5 font-semibold text-ink/70 hover:text-ink">Play against the line</button>
        </form>
    </div>
</x-layouts.app>
