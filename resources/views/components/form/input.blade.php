@props(['name', 'label', 'type' => 'text'])

<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-ink/80">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name) }}" @endif
        {{ $attributes->class([
            'block w-full rounded-lg border bg-white px-3.5 py-2.5 text-ink outline-none transition focus:ring-4',
            'border-line focus:border-club focus:ring-club/10' => ! $errors->has($name),
            'border-red-500 focus:border-red-500 focus:ring-red-500/15' => $errors->has($name),
        ]) }}
    >
    @error($name)
        <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>
