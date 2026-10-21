<button {{ $attributes->merge(['type' => 'submit'])->class('w-full rounded-lg bg-club px-4 py-3 font-semibold text-ivory shadow-[inset_0_-2px_0_rgb(0_0_0/0.15)] transition hover:bg-club-dark focus:outline-none focus:ring-4 focus:ring-grass/40') }}>
    {{ $slot }}
</button>
