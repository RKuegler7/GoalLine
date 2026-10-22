{{-- Simple line icons for navigation, drawn on a 24-unit grid. --}}
@props(['name'])

<svg {{ $attributes->class('shrink-0') }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('clubhouse')
            <path d="M3.5 10.5 12 4l8.5 6.5V19a1 1 0 0 1-1 1H15v-5.5H9V20H4.5a1 1 0 0 1-1-1z" />
            @break
        @case('leagues')
            <path d="M8 4.5h8v4.5a4 4 0 0 1-8 0z" />
            <path d="M8 6.5H5.5a2.5 2.5 0 0 0 2.6 3.4M16 6.5h2.5a2.5 2.5 0 0 1-2.6 3.4" />
            <path d="M12 13v3.5M8.5 20h7M10 16.5h4V20h-4z" />
            @break
        @case('highlights')
            <path d="M12 3.8l2.3 5 5.4.6-4 3.7 1.1 5.3L12 15.7l-4.8 2.7 1.1-5.3-4-3.7 5.4-.6z" />
            @break
        @case('calendar')
            <rect x="4" y="5.5" width="16" height="14.5" rx="2" />
            <path d="M4 10h16M8.5 3.5v4M15.5 3.5v4" />
            @break
        @case('repeat')
            <path d="M17 3.5l3 3-3 3" />
            <path d="M4 11.5v-1a4 4 0 0 1 4-4h12" />
            <path d="M7 20.5l-3-3 3-3" />
            <path d="M20 12.5v1a4 4 0 0 1-4 4H4" />
            @break
        @case('bell')
            <path d="M6 16.5V11a6 6 0 0 1 12 0v5.5l1.5 2h-15z" />
            <path d="M10 20.5a2 2 0 0 0 4 0" />
            @break
        @case('flame')
            <path d="M12 21c-3.9 0-6.5-2.7-6.5-6 0-4 4-5.5 3.5-10.5 3 1.8 4.7 4.2 4.7 7 1.2-.6 1.8-1.8 1.8-3 2.4 1.8 3 4 3 6.5 0 3.3-2.6 6-6.5 6z" />
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14" />
            @break
    @endswitch
</svg>
