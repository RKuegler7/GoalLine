{{-- An empty list: a small line-and-dot, one process-focused line, and one clear action. The action is a link
     (action + href), or a slot for a button or form. No sad faces, no shaming: an empty list is just a starting line. --}}
@props(['message', 'title' => null, 'action' => null, 'href' => null])

<div data-empty-state {{ $attributes->class('flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border border-dashed border-club/25 bg-white px-3.5 py-3') }}>
    <svg viewBox="0 0 44 16" class="h-4 w-11 shrink-0" aria-hidden="true">
        <line x1="2" y1="8" x2="34" y2="8" stroke="#43b96a" stroke-opacity="0.35" stroke-width="3" stroke-linecap="round" />
        <line x1="2" y1="8" x2="22" y2="8" stroke="#43b96a" stroke-width="3" stroke-linecap="round" />
        <line x1="40" y1="2" x2="40" y2="14" stroke="#176b45" stroke-width="2.5" stroke-linecap="round" />
        <circle cx="22" cy="8" r="4.5" fill="#176b45" />
    </svg>
    <div class="min-w-[12rem] flex-1">
        @if ($title)
            <p class="text-[15px] font-bold text-club">{{ $title }}</p>
        @endif
        <p class="text-sm text-ink/75">{{ $message }}</p>
    </div>
    @if ($action && $href)
        <a href="{{ $href }}" data-empty-action class="inline-flex min-h-10 shrink-0 items-center rounded-full bg-club px-4 text-[13px] font-semibold text-ivory hover:bg-club-dark">{{ $action }}</a>
    @elseif ($slot->isNotEmpty())
        <div data-empty-action class="shrink-0">{{ $slot }}</div>
    @endif
</div>
