{{-- When something couldn't load: the line with the dot waiting at the start, a calm sentence, and "Try again". No
     technical text. The script that showed it decides what "Try again" does (data-load-retry). --}}
@props(['message' => 'That didn’t load. Check your connection, then give it another go.'])

<div {{ $attributes->class('gl-load-error flex flex-col items-center gap-3 rounded-xl border border-line bg-white px-4 py-6 text-center') }} role="alert">
    <svg viewBox="0 0 120 16" class="h-4 w-28" aria-hidden="true">
        <line x1="4" y1="8" x2="104" y2="8" stroke="#43b96a" stroke-opacity="0.35" stroke-width="3" stroke-linecap="round" />
        <line x1="112" y1="2" x2="112" y2="14" stroke="#176b45" stroke-width="2.5" stroke-linecap="round" />
        <circle cx="8" cy="8" r="5" fill="#176b45" />
    </svg>
    <p data-load-error-message class="max-w-xs text-sm text-ink/80">{{ $message }}</p>
    <button type="button" data-load-retry class="inline-flex min-h-11 items-center rounded-full bg-club px-5 text-sm font-semibold text-ivory hover:bg-club-dark">Try again</button>
</div>
