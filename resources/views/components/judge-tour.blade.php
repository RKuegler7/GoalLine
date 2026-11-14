{{-- The Judge's tour, in demo accounts only: a small floating pill ("Judge's tour · 3 of 6") that opens a sheet with
     six things worth trying. Each has a one-line explanation and "Take me there", and ticks itself off when done
     (JudgeTour). It never blocks the page and can be hidden; Reset demo starts it fresh. --}}
@php
    $viewer = auth()->user();
    $show = App\Services\JudgeTour::applies($viewer) && ! App\Services\JudgeTour::hidden($viewer);
    $done = $show ? App\Services\JudgeTour::done($viewer) : [];
    $total = count(App\Services\JudgeTour::ITEMS);
@endphp

@if ($show)
    <div data-judge-tour>
        <button type="button" data-judge-tour-open aria-haspopup="dialog"
            class="fixed bottom-[calc(4.5rem+env(safe-area-inset-bottom))] left-[max(1rem,env(safe-area-inset-left))] z-30 inline-flex min-h-12 items-center gap-2 rounded-full border border-gold bg-white pr-4 pl-3 text-sm font-semibold text-ink shadow-lg shadow-ink/10 hover:bg-ivory md:bottom-6">
            <span class="grid size-7 place-items-center rounded-full bg-gold text-[13px] font-extrabold text-ink tabular-nums" aria-hidden="true">{{ count($done) }}</span>
            <span>Judge’s tour <span class="font-normal text-ink/70 tabular-nums">{{ count($done) }} of {{ $total }}</span></span>
        </button>

        <dialog data-judge-tour-sheet aria-labelledby="judge-tour-title"
            class="gl-sheet m-0 mt-auto w-full max-w-none rounded-t-2xl bg-ivory p-0 text-ink shadow-2xl backdrop:bg-ink/40 sm:m-auto sm:max-w-lg sm:rounded-2xl">
            <div class="flex items-center justify-between gap-3 border-b border-line bg-white px-4 py-1.5">
                <h2 id="judge-tour-title" class="text-[18px] font-bold text-club">Judge’s tour</h2>
                <p class="ml-auto text-[13px] font-semibold text-ink/75 tabular-nums" aria-live="polite">{{ count($done) }} of {{ $total }} done</p>
                <form method="dialog">
                    <button type="submit" class="grid size-11 place-items-center rounded-full text-ink/70 hover:bg-ivory" aria-label="Close"><x-icon name="x" class="size-5" /></button>
                </form>
            </div>
            <div data-sheet-scroll class="max-h-[78dvh] overflow-y-auto px-4 pt-3 pb-[max(1.25rem,env(safe-area-inset-bottom))]">
                <div class="h-2 overflow-hidden rounded-full bg-line" role="progressbar" aria-label="Tour progress" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ count($done) }}">
                    <div class="h-full rounded-full bg-gold" style="width: {{ round(count($done) / $total * 100) }}%"></div>
                </div>
                <p class="mt-2 text-[13px] text-ink/75">Six things that show what GoalLine does. Each one ticks itself off when you do it.</p>
                <ol class="mt-3 space-y-2">
                    @foreach (App\Services\JudgeTour::ITEMS as $key => $item)
                        @php $isDone = in_array($key, $done, true); @endphp
                        <li @class(['flex items-start gap-3 rounded-xl border bg-white px-3 py-2.5', 'border-grass' => $isDone, 'border-line' => ! $isDone]) data-judge-tour-item="{{ $key }}" @if ($isDone) data-done @endif>
                            <span @class(['mt-0.5 grid size-6 shrink-0 place-items-center rounded-full text-[13px] font-bold', 'bg-grass text-ink' => $isDone, 'bg-ivory text-ink/70 ring-1 ring-line ring-inset' => ! $isDone]) aria-hidden="true">{{ $isDone ? '✓' : $loop->iteration }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[14px] font-semibold text-ink">{{ $item['title'] }} <span class="sr-only">({{ $isDone ? 'done' : 'not done yet' }})</span></p>
                                <p class="mt-0.5 text-[13px] leading-snug text-ink/75">{{ $item['explain'] }}</p>
                                <a href="{{ route('judge-tour.go', $key) }}" class="mt-1 inline-flex min-h-11 items-center text-[13px] font-semibold text-club hover:underline">{{ $isDone ? 'Go again' : 'Take me there' }} &rarr;</a>
                            </div>
                        </li>
                    @endforeach
                </ol>
                <form method="POST" action="{{ route('judge-tour.hide') }}" class="mt-3 text-center" data-live="false">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 items-center text-[13px] font-semibold text-ink/70 hover:text-ink hover:underline">Hide the tour</button>
                </form>
            </div>
        </dialog>
    </div>
@endif
