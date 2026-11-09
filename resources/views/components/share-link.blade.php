{{-- A link people copy or share: the full absolute address in a field, Copy (with "Copied ✓" and a toast), and
     Share for the phone's share sheet when there's a message. If the browser blocks copying, the field is shown and
     selected so it can be copied by hand. "compact" keeps the field out of sight until then. --}}
@props(['url', 'id', 'label' => 'Link', 'shareMessage' => null, 'shareTitle' => 'GoalLine', 'compact' => false, 'copyLabel' => 'Copy link'])

<div data-share-link {{ $attributes->class(['min-w-0']) }}>
    <label for="{{ $id }}" @class(['text-sm text-ink/70', 'sr-only' => $compact])>{{ $label }}</label>
    <div @class(['flex flex-wrap items-center gap-2', 'mt-1' => ! $compact])>
        <input id="{{ $id }}" type="text" readonly value="{{ $url }}" onclick="this.select()" data-share-link-field
            @class(['block min-w-0 flex-1 basis-48 rounded-lg border border-line bg-ivory px-3 py-2 text-sm text-ink/80 outline-none focus:border-club', 'sr-only' => $compact])>
        <button type="button" data-copy-from="{{ $id }}" aria-label="{{ $copyLabel }}: {{ $label }}"
            class="min-h-10 shrink-0 rounded-lg border border-line px-3 py-2 text-sm font-semibold text-club transition hover:border-club/40 data-copied:border-club data-copied:bg-mint">{{ $copyLabel }}</button>
        @if ($shareMessage)
            <button type="button" data-share-message="{{ $shareMessage }}" data-share-title="{{ $shareTitle }}" hidden
                class="min-h-10 shrink-0 rounded-lg bg-club px-3 py-2 text-sm font-semibold text-ivory hover:bg-club-dark">Share</button>
        @endif
    </div>
    <p data-copy-fallback hidden class="mt-1 text-xs text-ink/70">Your browser blocked copying. The link is selected above: copy it with Ctrl+C, or tap and hold, then Copy.</p>
</div>
