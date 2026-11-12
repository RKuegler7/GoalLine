{{-- "Details added": a check-in's caption, place and time ("6:30 to 7:15 a.m. at Rock Creek Park"). A soft marker,
     never the gold Verified check: only a photo or an opponent's or league confirmation earns that. Show it only to
     people who can see the proof (RoadmapTask::detailsVisibleTo). --}}
@props(['task'])

<p {{ $attributes->class('flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs text-ink/75') }} data-proof-details>
    <span class="inline-flex items-center gap-1 rounded-full bg-ivory px-2 py-0.5 font-semibold text-ink/70 ring-1 ring-line ring-inset">
        <svg viewBox="0 0 16 16" class="size-3" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 4h10M3 8h10M3 12h6" /></svg>
        Details added
    </span>
    @if ($task->detailsLine())
        <span>{{ $task->detailsLine() }}</span>
    @endif
    @if ($task->proof_caption)
        <span class="text-ink/80">&ldquo;{{ $task->proof_caption }}&rdquo;</span>
    @endif
</p>
