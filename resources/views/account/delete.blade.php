<x-layouts.app title="Delete account · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <a href="{{ route('profile.edit') }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; Edit profile</a>
            <h1 class="mt-6 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">Delete your account?</h1>
        </div>
    </x-slot:hero>

    <form method="POST" action="{{ route('account.destroy') }}" class="mx-auto max-w-xl space-y-5 rounded-2xl border border-line bg-white p-6 sm:p-10">
        @csrf
        @method('DELETE')
        <p class="text-lg text-ink">This permanently deletes everything:</p>
        <ul class="list-disc space-y-1 pl-5 text-ink/75">
            <li>Your goals, roadmaps, check-ins, and uploaded plans</li>
            <li>Your photos, posts, highlights, and kudos</li>
            <li>Your GoalLeague memberships and chat messages</li>
        </ul>
        <p class="text-ink/70">It can't be undone. If you just need a break, you can retire goals instead.</p>
        <x-form.input name="password" label="Enter your password to confirm" type="password" autocomplete="current-password" required />
        <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end">
            <a href="{{ route('profile.edit') }}" class="rounded-lg px-5 py-3 text-center font-semibold text-club hover:bg-mint">Keep my account</a>
            <button type="submit" class="rounded-lg bg-red-700 px-5 py-3 font-semibold text-white hover:bg-red-800">Delete my account</button>
        </div>
    </form>
</x-layouts.app>
