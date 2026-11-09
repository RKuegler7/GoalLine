{{-- A loading placeholder: grey rows that hold the space, so nothing jumps when content arrives. --}}
@props(['rows' => 3])

<div {{ $attributes->class('space-y-2') }} aria-hidden="true">
    @for ($row = 0; $row < $rows; $row++)
        <div class="gl-skeleton h-10 rounded-xl"></div>
    @endfor
</div>
