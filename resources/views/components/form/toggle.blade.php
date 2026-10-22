{{-- An on/off switch that always submits a value: 1 when on, 0 when off. --}}
@props(['name', 'checked' => false, 'label'])

<label class="relative inline-flex cursor-pointer items-center">
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="1" class="peer sr-only" @checked($checked)>
    <span class="sr-only">{{ $label }}</span>
    <span class="relative h-6 w-11 shrink-0 rounded-full bg-line transition peer-checked:bg-club peer-focus-visible:ring-4 peer-focus-visible:ring-club/20 after:absolute after:top-0.5 after:left-0.5 after:size-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5" aria-hidden="true"></span>
</label>
