@if (auth()->user()->isDemo())
    <a href="{{ route('finale.preview') }}" class="flex min-h-11 items-center justify-between gap-3 rounded-xl bg-gold px-3 py-2 text-ink hover:brightness-105" data-preview-finale>
        <span class="text-[14px] font-bold">Preview season finale</span>
        <span class="text-[13px] font-semibold">CPA Prep League &rarr;</span>
    </a>
@endif
