{{-- The Help center's content: search, the FAQ (from resources/help/faq.php) as expandable groups, the tour, scoring,
     and feedback. Fetched into the Help sheet, or shown on its own page without scripts. $focus opens the answer that
     fits the screen Help was opened from. --}}
<div data-help-panel class="space-y-4 text-[14px]">
    <div>
        <label for="help-search" class="sr-only">Search help</label>
        <input id="help-search" type="search" data-help-search placeholder="Search questions, like &ldquo;dispute&rdquo; or &ldquo;lighter week&rdquo;" autocomplete="off"
            class="block w-full rounded-full border border-line bg-white px-4 py-2.5 text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10">
        <p data-help-empty hidden class="mt-2 text-[13px] text-ink/70">No questions match that yet. Try another word, or send us a note below.</p>
    </div>

    <div class="flex flex-wrap gap-2">
        <button type="button" data-tour-start class="inline-flex min-h-11 items-center gap-1.5 rounded-full bg-club px-4 text-[14px] font-semibold text-ivory hover:bg-club-dark">
            <x-icon name="flag" class="size-4" /> Take the tour
        </button>
        <a href="{{ route('about') }}#scoring" data-open-sheet="scoring-sheet" data-scoring-help class="inline-flex min-h-11 items-center gap-1.5 rounded-full border border-club/30 px-4 text-[14px] font-semibold text-club hover:border-club">
            <x-icon name="info" class="size-4" /> How is GoalLine scored?
        </a>
    </div>

    <div class="space-y-2" data-help-faq>
        @foreach ($faq as $group)
            @php($groupHasFocus = $focus && in_array($focus, array_column($group['items'], 'id'), true))
            <section aria-labelledby="help-group-{{ $group['id'] }}" data-help-group class="rounded-xl border border-line bg-white">
                <h3 id="help-group-{{ $group['id'] }}" class="eyebrow px-3 pt-2.5 pb-1 text-[11px] text-club">{{ $group['title'] }}</h3>
                <div class="divide-y divide-line">
                    @foreach ($group['items'] as $item)
                        <details id="help-{{ $item['id'] }}" data-help-item data-help-text="{{ Str::lower($item['q'].' '.$item['a']) }}" @if ($focus === $item['id']) open data-help-focus @endif
                            class="group px-3">
                            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 py-2 font-semibold text-ink focus-visible:outline-2 focus-visible:outline-club [&::-webkit-details-marker]:hidden">
                                <span>{{ $item['q'] }}</span>
                                <x-icon name="chevron-down" class="size-4 shrink-0 text-ink/60 transition group-open:rotate-180" />
                            </summary>
                            <p class="pb-3 text-ink/80">{{ $item['a'] }}</p>
                        </details>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    <section aria-labelledby="help-feedback" class="rounded-xl border border-line bg-white px-3 py-3">
        <h3 id="help-feedback" class="text-[15px] font-bold text-club">Report a problem or send feedback</h3>
        <form method="POST" action="{{ route('feedback.store') }}" class="mt-2 space-y-2" data-help-feedback>
            @csrf
            <input type="hidden" name="page" value="{{ $page }}" data-help-page>
            <label for="help-feedback-body" class="sr-only">Your note</label>
            <textarea id="help-feedback-body" name="body" rows="3" maxlength="2000" required placeholder="What happened, or what would make GoalLine better?"
                class="block w-full rounded-lg border border-line bg-white px-3 py-2 text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10"></textarea>
            <p class="text-xs text-ink/70">It goes to the GoalLine team with the page you're on. Nobody else sees it.</p>
            <button type="submit" class="inline-flex min-h-11 items-center rounded-full bg-club px-4 text-sm font-semibold text-ivory hover:bg-club-dark">Send</button>
        </form>
    </section>

    <p class="flex flex-wrap gap-x-4 gap-y-1 text-[13px] font-semibold">
        <a href="{{ route('about') }}" class="text-club hover:underline">About GoalLine</a>
        <a href="{{ route('privacy') }}" class="text-club hover:underline">Privacy &amp; safety</a>
    </p>

    {{-- The scoring sheet, for pages that don't already have it (moved into the page by help.js). --}}
    <template data-scoring-sheet-template><x-scoring-sheet /></template>
</div>
