{{--
    G monogram app icon on a 512-unit rounded square: the GoalLine "G" with the signature Line beneath.
    Flat, no gradients or shadows. The "favicon" variant enlarges everything so it stays legible at 16-32px.
--}}
@props(['variant' => 'icon'])

@php
    $favicon = $variant === 'favicon';

    // The G glyph spans x 5-77.2 and y 24.3-101.2 at font size 100.
    $scale = $favicon ? 3.6 : 3.2;
    $glyphWidth = 72.2 * $scale;
    $glyphHeight = 76.9 * $scale;
    $gap = $favicon ? 26 : 30;
    $postHeight = $favicon ? 80 : 64;
    $top = (512 - ($glyphHeight + $gap + $postHeight)) / 2;

    $left = (512 - $glyphWidth) / 2;
    $translateX = round($left - 5 * $scale, 2);
    $translateY = round($top - 24.3 * $scale, 2);

    $lineY = round($top + $glyphHeight + $gap + $postHeight / 2, 2);
    $lineStart = round($left + 6, 2);
    $lineEnd = round($left + $glyphWidth + 24, 2);
    $markerX = round($lineStart + ($lineEnd - $lineStart) * 0.62, 2);
    $lineStroke = $favicon ? 30 : 18;
    $markerRadius = $favicon ? 34 : 26;
@endphp

<svg {{ $attributes }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" role="img" aria-label="GoalLine">
    <rect width="512" height="512" rx="112" fill="#176b45" />

    <x-brand.g-letter fill="#fafaf5" transform="translate({{ $translateX }} {{ $translateY }}) scale({{ $scale }})" />

    <g stroke-width="{{ $lineStroke }}" stroke-linecap="round">
        <path d="M{{ $lineStart }} {{ $lineY }} H{{ $markerX }}" stroke="#43b96a" />
        <path d="M{{ $markerX }} {{ $lineY }} H{{ $lineEnd }}" stroke="#e5f3e8" stroke-opacity="0.6" />
        <path d="M{{ $lineEnd }} {{ $lineY - $postHeight / 2 }} V{{ $lineY + $postHeight / 2 }}" stroke="#e5f3e8" />
    </g>
    <circle cx="{{ $markerX }}" cy="{{ $lineY }}" r="{{ $markerRadius }}" fill="#43b96a" />
</svg>
