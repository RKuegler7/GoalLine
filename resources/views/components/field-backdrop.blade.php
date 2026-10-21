{{--
    The signature illustrated pitch: broad mowed bands seen with gentle perspective.
    "dark" sits behind white type on Club Green; "light" is a soft mint version for ivory sections.
    Decorative only.
--}}
@props(['variant' => 'dark'])

@php
    $id = uniqid('field-');
    $dark = $variant === 'dark';
    $stripes = $dark ? ['#1b724a', '#26875a'] : ['#e5f3e8', '#eff8f1'];
    $haze = $dark ? '#0f4d31' : '#fafaf5';
    $marking = $dark ? '#fafaf5' : '#176b45';
@endphp

<svg {{ $attributes->class('pointer-events-none') }} viewBox="0 0 1440 640" preserveAspectRatio="xMidYMax slice" aria-hidden="true" focusable="false">
    <defs>
        <linearGradient id="{{ $id }}-haze" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="{{ $haze }}" stop-opacity="{{ $dark ? 0.75 : 0.9 }}" />
            <stop offset="0.6" stop-color="{{ $haze }}" stop-opacity="0.15" />
            <stop offset="1" stop-color="{{ $haze }}" stop-opacity="0" />
        </linearGradient>
        <radialGradient id="{{ $id }}-light" cx="0.5" cy="1" r="0.75">
            <stop offset="0" stop-color="#e5f3e8" stop-opacity="{{ $dark ? 0.1 : 0.5 }}" />
            <stop offset="1" stop-color="#e5f3e8" stop-opacity="0" />
        </radialGradient>
    </defs>

    @for ($stripe = -11; $stripe <= 10; $stripe++)
        <polygon
            points="{{ 720 + $stripe * 78 }},0 {{ 720 + ($stripe + 1) * 78 }},0 {{ 720 + ($stripe + 1) * 190 }},640 {{ 720 + $stripe * 190 }},640"
            fill="{{ $stripes[abs($stripe) % 2] }}"
        />
    @endfor

    <rect width="1440" height="640" fill="url(#{{ $id }}-haze)" />
    <rect width="1440" height="640" fill="url(#{{ $id }}-light)" />

    {{-- A faint arc resting on the goal line. --}}
    <ellipse cx="720" cy="640" rx="250" ry="76" fill="none" stroke="{{ $marking }}" stroke-opacity="{{ $dark ? 0.16 : 0.12 }}" stroke-width="2" />
</svg>
