{{-- A small "+ Start" style menu: one pill button that opens a short list of actions. Keeps create and join actions
     out of the way so lists come first. Pass <a> items in the slot. --}}
@props(['label' => 'Start', 'icon' => 'plus', 'tone' => 'light'])

<details {{ $attributes->class('relative') }} data-action-menu>
    <summary @class([
        'gl-tap relative inline-flex min-h-9 cursor-pointer list-none items-center gap-1 rounded-full px-3 text-[13px] font-semibold transition [&::-webkit-details-marker]:hidden',
        'bg-ivory text-club hover:bg-white' => $tone === 'light',
        'bg-club text-ivory hover:bg-club-dark' => $tone === 'dark',
    ])>
        <x-icon :name="$icon" class="size-4" /> {{ $label }}
    </summary>
    <div class="absolute right-0 z-50 mt-1.5 w-56 overflow-hidden rounded-xl border border-line bg-white py-1 text-sm text-ink shadow-lg [&>a]:flex [&>a]:min-h-11 [&>a]:items-center [&>a]:gap-2.5 [&>a]:px-3.5 [&>a:hover]:bg-mint">
        {{ $slot }}
    </div>
</details>
