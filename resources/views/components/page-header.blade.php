{{-- The compact page header inside the app: an optional back link, the title (20px on phones, 24px on desktop), an
     optional subtitle that wraps in full (never cut off), and small actions on the right.
     Use it in a page's hero slot: <x-slot:hero><x-page-header title="GoalLeagues">…actions…</x-page-header></x-slot:hero> --}}
@props(['title', 'subtitle' => null, 'eyebrow' => null, 'back' => null, 'backLabel' => 'Back'])

<div {{ $attributes->class('mx-auto flex min-h-12 max-w-5xl items-center justify-between gap-3 px-4 pt-0.5 pb-3') }}>
    <div class="min-w-0">
        {{-- Back lives in the top bar now (see BackNavigation); a page that names its parent here overrides the default. --}}
        @if ($back)
            @php(request()->attributes->set('page_back', ['url' => $back, 'label' => $backLabel]))
        @endif
        @if ($eyebrow)
            <p class="eyebrow [overflow-wrap:anywhere] text-[11px] text-mint">{{ $eyebrow }}</p>
        @endif
        <h1 class="[overflow-wrap:anywhere] font-serif text-[20px] leading-tight font-bold tracking-tight text-ivory md:text-[24px]">{{ $title }}</h1>
        @if ($subtitle)
            <p class="[overflow-wrap:anywhere] text-[13px] text-ivory/85">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex shrink-0 items-center gap-2">{{ $slot }}</div>
    @endif
</div>
