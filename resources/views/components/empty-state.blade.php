{{-- An empty list: one short line and one action. --}}
@props(['message', 'action' => null, 'href' => null])

<div {{ $attributes->class('flex flex-wrap items-center justify-between gap-2 rounded-xl border border-dashed border-line bg-white px-3.5 py-3 text-sm text-ink/70') }}>
    <span>{{ $message }}</span>
    @if ($action && $href)
        <a href="{{ $href }}" class="inline-flex min-h-9 items-center rounded-full bg-club px-3 text-[13px] font-semibold text-ivory hover:bg-club-dark">{{ $action }}</a>
    @endif
</div>
