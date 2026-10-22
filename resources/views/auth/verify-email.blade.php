<x-layouts.app title="Verify your email · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <p class="eyebrow text-mint">One quick step</p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">Verify your email</h1>
        </div>
    </x-slot:hero>

    <div class="mx-auto max-w-xl rounded-2xl border border-line bg-white p-6 text-center sm:p-10">
        <p class="text-lg text-ink">We sent a link to <span class="font-semibold">{{ auth()->user()->email }}</span>.</p>
        <p class="mt-2 text-ink/70">
            Your goals and roadmaps work right away. Verifying unlocks GoalLeagues, Highlights, and following friends, so everyone you play with is a real person.
        </p>
        <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
            @csrf
            <button type="submit" class="rounded-full bg-club px-6 py-2.5 font-semibold text-ivory hover:bg-club-dark">Send the link again</button>
        </form>
        <p class="mt-4 text-sm text-ink/55">Can't find it? Check spam, or <a href="{{ route('profile.edit') }}" class="font-semibold text-club hover:underline">fix your email address</a>.</p>
    </div>
</x-layouts.app>
