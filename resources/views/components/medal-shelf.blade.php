{{-- A row of medals, most special first. $medals: a collection of App\Models\Medal. Each shows its symbol, name with
     tier, and why it was awarded (the rules live in config/medals.php). --}}
@props(['medals', 'compact' => false])

<ul {{ $attributes->class(['grid gap-2', 'sm:grid-cols-2' => ! $compact]) }} data-medal-shelf>
    @foreach ($medals as $medal)
        <li class="flex items-start gap-2.5 rounded-xl bg-ivory px-3 py-2 ring-1 ring-line ring-inset" data-medal="{{ $medal->type->value }}">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-gold/30 text-[18px] ring-1 ring-gold" aria-hidden="true">{{ $medal->symbol() }}</span>
            <span class="min-w-0">
                <span class="block text-[14px] font-semibold text-ink">{{ $medal->title() }}</span>
                @unless ($compact)
                    <span class="block text-[12px] text-ink/75">{{ $medal->detail() }}@if ($medal->league) &middot; {{ $medal->league->name }}@endif</span>
                @endunless
            </span>
        </li>
    @endforeach
</ul>
