<x-layouts.app :title="'Feed · '.$league->name.' · GoalLine'">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <a href="{{ route('leagues.show', $league) }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; {{ $league->name }}</a>
            <p class="eyebrow mt-6 text-mint">League feed</p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">The work behind the wins</h1>
            <p class="mt-3 max-w-xl text-ivory/85">Every photo here is a check-in someone showed. Cheer the process, not just the result.</p>
        </div>
    </x-slot:hero>

    <div class="mx-auto max-w-2xl">
        @if ($reports->isNotEmpty())
            <section class="mb-8 rounded-2xl border border-red-200 bg-red-50/60 px-5 py-4">
                <h2 class="eyebrow text-red-800">Reports to review</h2>
                <p class="mt-1 text-sm text-ink/70">Only you see this, as league owner. Reported posts are hidden from whoever reported them. Remove a post from the league with its &hellip; menu, or leave it up if it's fine.</p>
                <ul class="mt-3 space-y-3">
                    @foreach ($reports as $postReports)
                        @php($reported = $postReports->first()->highlight)
                        <li class="rounded-xl border border-line bg-white px-4 py-3 text-sm">
                            <p>
                                <a href="#highlight-{{ $reported->id }}" class="font-semibold text-ink hover:text-club hover:underline">{{ $reported->user->name }}: {{ Str::limit($reported->detail, 60) }}</a>
                                <span class="text-ink/50">&middot; {{ $postReports->count() }} {{ Str::plural('report', $postReports->count()) }}</span>
                            </p>
                            <ul class="mt-1 space-y-0.5 text-ink/70">
                                @foreach ($postReports as $report)
                                    <li>{{ $report->reporter->name }}: {{ $report->reason ?: 'No reason given' }} <span class="text-ink/45">&middot; {{ App\Clock::shortTime($report->created_at) }}</span></li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($posts->isEmpty() && $posts->onFirstPage())
            <section class="overflow-hidden rounded-2xl border border-line bg-white">
                <div class="relative h-28 overflow-hidden bg-club">
                    <x-field-backdrop class="absolute inset-0 size-full" />
                </div>
                <x-goal-line />
                <div class="px-8 py-8 text-center">
                    <h2 class="font-serif text-2xl font-medium text-club">No posts yet. Be the first.</h2>
                    <p class="mx-auto mt-2 max-w-md text-ink/70">
                        Check off a task, then tap &ldquo;Show your work&rdquo; to add a photo. It earns a Verified check, shows your league the process, and gets you cheers.
                    </p>
                    <a href="{{ route('dashboard') }}" class="mt-5 inline-block rounded-full bg-club px-5 py-2 text-sm font-semibold text-ivory hover:bg-club-dark">Go to today's Gameplan</a>
                </div>
            </section>
        @else
            <div class="space-y-5">
                @foreach ($posts as $post)
                    <x-highlight-card :highlight="$post" />
                @endforeach
            </div>
            <nav class="mt-6 flex justify-between text-sm font-semibold">
                @if ($posts->previousPageUrl())
                    <a href="{{ $posts->previousPageUrl() }}" class="rounded-full border border-line bg-white px-5 py-2 text-club hover:border-club/40">&larr; Newer</a>
                @else
                    <span></span>
                @endif
                @if ($posts->hasMorePages())
                    <a href="{{ $posts->nextPageUrl() }}" class="rounded-full border border-line bg-white px-5 py-2 text-club hover:border-club/40">Older &rarr;</a>
                @endif
            </nav>
        @endif
    </div>
</x-layouts.app>
