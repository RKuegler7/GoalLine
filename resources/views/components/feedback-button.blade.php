{{-- "Feedback" for beta testers: a small button that opens a note box, sent with the page it came from. --}}
<details {{ $attributes->class('group fixed right-4 bottom-24 z-30 md:bottom-6') }} data-feedback>
    <summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-full bg-ink px-4 py-2 text-sm font-semibold text-ivory shadow-lg shadow-ink/20 transition hover:bg-club [&::-webkit-details-marker]:hidden">
        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5.5h16v10H9l-5 4z" /></svg>
        <span class="group-open:hidden">Feedback</span>
        <span class="hidden group-open:inline">Close</span>
    </summary>
    <form method="POST" action="{{ route('feedback.store') }}" class="absolute right-0 bottom-12 w-[min(22rem,calc(100vw-2rem))] rounded-2xl border border-line bg-white p-4 shadow-xl">
        @csrf
        <input type="hidden" name="page" value="{{ request()->path() === '/' ? '/' : '/'.request()->path() }}">
        <label for="feedback-body" class="font-serif text-lg font-medium text-club">Tell us anything</label>
        <p class="text-xs text-ink/55">Something broken, confusing, or great? You&rsquo;re shaping GoalLine.</p>
        <textarea id="feedback-body" name="body" rows="4" maxlength="2000" required placeholder="The planner was confusing when..."
            class="mt-2 block w-full rounded-xl border border-line bg-ivory/60 px-3 py-2 text-sm text-ink outline-none placeholder:text-ink/35 focus:border-club focus:bg-white focus:ring-4 focus:ring-club/10"></textarea>
        <button type="submit" class="mt-3 w-full rounded-full bg-club px-4 py-2 text-sm font-semibold text-ivory hover:bg-club-dark">Send feedback</button>
    </form>
</details>
