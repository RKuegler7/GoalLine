{{-- The compact page header inside the app: an optional back link, the title (20px on phones, 24px on desktop), an
     optional one-line subtitle, and small actions on the right. About 48px tall, so lists start right under it.
     Use it in a page's hero slot: <x-slot:hero><x-page-header title="GoalLeagues">…actions…</x-page-header></x-slot:hero> --}}
@props(['title', 'subtitle' => null, 'eyebrow' => null, 'back' => null, 'backLabel' => 'Back'])

<div {{ $attributes->class('mx-auto flex min-h-12 max-w-5xl items-center justify-between gap-3 px-4 pt-0.5 pb-3') }}>
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="gl-tap relative -ml-1 inline-flex min-h-8 items-center gap-0.5 rounded-full pr-2 text-xs font-semibold text-ivory/85 hover:text-ivory">
                <x-icon name="chevron-left" class="size-4" /> {{ $backLabel }}
            </a>
        @endif
        @if ($eyebrow)
            <p class="eyebrow truncate text-[11px] text-mint">{{ $eyebrow }}</p>
        @endif
        <h1 class="truncate font-serif text-[20px] leading-tight font-bold tracking-tight text-ivory md:text-[24px]">{{ $title }}</h1>
        @if ($subtitle)
            <p class="truncate text-[13px] text-ivory/85">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex shrink-0 items-center gap-2">{{ $slot }}</div>
    @endif
</div>
