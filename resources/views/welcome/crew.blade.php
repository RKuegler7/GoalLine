<x-layouts.welcome :step="3" title="Who's in your corner?" intro="Goals stick when people are watching. Join a GoalLeague, start one, or follow friends. All optional.">
    <div class="space-y-4">
        @unless ($user->hasVerifiedEmail())
            <p class="rounded-xl border border-gold/50 bg-gold/15 px-4 py-3 text-sm text-ink">
                Verify your email first to join GoalLeagues and follow friends. We sent a link to {{ $user->email }}.
            </p>
        @endunless

        <form method="POST" action="{{ route('leagues.find') }}" class="rounded-2xl border border-line bg-white px-6 py-5">
            @csrf
            <label for="code" class="eyebrow text-club">Got an invite code?</label>
            <div class="mt-3 flex gap-2">
                <input id="code" name="code" required placeholder="K7QX-M4PA" autocomplete="off" autocapitalize="characters"
                    class="min-w-0 flex-1 rounded-lg border border-line bg-white px-3.5 py-2.5 font-mono tracking-widest uppercase outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                <button type="submit" class="rounded-lg bg-club px-4 py-2.5 font-semibold text-ivory hover:bg-club-dark">Find</button>
            </div>
            @error('code')
                <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </form>

        <form method="GET" action="{{ route('people.index') }}" class="rounded-2xl border border-line bg-white px-6 py-5">
            <label for="q" class="eyebrow text-club">Find friends</label>
            <div class="mt-3 flex gap-2">
                <input id="q" name="q" placeholder="Name or @username" class="min-w-0 flex-1 rounded-lg border border-line bg-white px-3.5 py-2.5 outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                <button type="submit" class="rounded-lg border border-line px-4 py-2.5 font-semibold text-club hover:border-club/40">Search</button>
            </div>
            <p class="mt-2 text-sm text-ink/55">You can also start your own GoalLeague from the GoalLeagues tab once you're in.</p>
        </form>

        <div class="flex justify-end pt-2">
            <a href="{{ route('welcome.rules') }}" class="rounded-full bg-club px-6 py-2.5 font-semibold text-ivory hover:bg-club-dark">Next: the rules</a>
        </div>
    </div>
</x-layouts.welcome>
