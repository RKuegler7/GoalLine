{{-- League mates' shared Goal Day results. Private results never reach this list. Cheers come from the Goal Day post. --}}
@php
    $posts = App\Models\Highlight::where('kind', App\HighlightKind::GoalDay)->whereIn('goal_id', $news->pluck('goal_id'))
        ->withCount('cheers')->get()->keyBy('goal_id');
@endphp
<ul class="mt-3 space-y-2" data-goal-day-news>
    @foreach ($news as $shared)
        @php $post = $posts->get($shared->goal_id); @endphp
        <li class="rounded-xl bg-ivory px-3 py-2.5 ring-1 ring-line ring-inset" data-goal-day-result>
            <div class="flex items-start gap-2.5">
                <x-avatar :user="$shared->user" />
                <div class="min-w-0 flex-1">
                    <p class="text-[14px] font-semibold text-ink">{{ $shared->user->name }}</p>
                    <p class="truncate text-[13px] text-ink/75">{{ $shared->goal->title }}</p>
                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[12px]">
                        <span @class(['eyebrow rounded-full px-2 py-0.5 text-[10px]', 'bg-gold text-ink' => $shared->isAchieved(), 'bg-mint text-club' => ! $shared->isAchieved()])>{{ $shared->outcome->label() }}</span>
                        @if ($shared->isVerified())
                            <span class="eyebrow rounded-full bg-club px-2 py-0.5 text-[10px] text-ivory">Verified</span>
                        @endif
                        @if ($shared->actual_result)
                            <span class="text-ink/75">{{ $shared->actual_result }}</span>
                        @endif
                    </div>
                    @if ($shared->helped_most)
                        <p class="mt-1 text-[13px] text-ink/80">“{{ $shared->helped_most }}”</p>
                    @endif
                </div>
            </div>
            @if ($interactive ?? true)
                <div class="mt-2 flex flex-wrap items-center gap-2 text-[13px]">
                    @if ($post && auth()->user()->can('view', $post))
                        @php $cheered = $post->isCheeredBy(auth()->user()); @endphp
                        <form method="POST" action="{{ route('highlights.cheer', $post) }}">
                            @csrf
                            <input type="hidden" name="intent" value="{{ $cheered ? 'remove' : 'add' }}">
                            <button type="submit" aria-pressed="{{ $cheered ? 'true' : 'false' }}" @class(['inline-flex min-h-9 items-center rounded-full px-3 font-semibold', 'bg-grass text-ink' => $cheered, 'ring-1 ring-club/30 text-club' => ! $cheered])>
                                {{ $cheered ? 'Cheered' : 'Cheer' }} &middot; {{ $post->cheers_count }}
                            </button>
                        </form>
                    @endif
                    @if ($shared->canBeConfirmedBy(auth()->user()))
                        <form method="POST" action="{{ route('goal-day.confirm', $shared) }}">
                            @csrf
                            <button type="submit" class="inline-flex min-h-9 items-center rounded-full px-3 font-semibold text-club ring-1 ring-club/30" data-confirm-result>Confirm it</button>
                        </form>
                    @endif
                </div>
            @endif
        </li>
    @endforeach
</ul>
