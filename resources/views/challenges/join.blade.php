{{-- Joining a Challenge (from a link, a code, an in-app invite or the public list): what it is, the rules, the dates and
     who's in, then "I'm in for these rules". Full, finished and members-only (side Challenge) states are friendly. --}}
<x-layouts.app :title="'Join '.$challenge->title.' · GoalLine'">
    <x-slot:hero>
        <x-page-header :title="$challenge->title" :eyebrow="'Challenge · '.$challenge->typeLabel()" :back="route('leagues.index', ['tab' => 'challenges'])" back-label="Challenges"
            :subtitle="'Started by '.$challenge->creator->name.' · '.$challenge->members_count.' '.Str::plural('member', $challenge->members_count).($challenge->max_members ? ' of '.$challenge->max_members : '')" />
    </x-slot:hero>

    <section class="mx-auto max-w-xl space-y-4 rounded-2xl border border-gold/60 bg-white p-4 sm:p-5" data-first-content data-challenge-join>
        @if ($challenge->description)
            <p class="text-[15px] whitespace-pre-line text-ink/85">{{ $challenge->description }}</p>
        @endif

        <div>
            <h2 class="eyebrow text-gold-ink">The rules</h2>
            <p class="mt-1.5 text-[17px] font-semibold text-ink">{{ $challenge->typeLabel() }}: {{ $challenge->ruleSummary() }}</p>
            <p class="mt-0.5 text-[13px] text-ink/75">{{ $challenge->typeExplanation() }}</p>
            @if ($challenge->type === App\Models\Challenge::DAILY && count($challenge->checklist()) > 1)
                <ul class="mt-1.5 list-inside list-disc text-[15px] text-ink/85">
                    @foreach ($challenge->checklist() as $rule)<li>{{ $rule }}</li>@endforeach
                </ul>
            @endif
            <ul class="mt-2 space-y-1 text-[13px] text-ink/80">
                <li>{{ $challenge->starts_on->format('l, M j') }} to {{ $challenge->ends_on->format('l, M j, Y') }} ({{ $challenge->lengthInDays() }} days). Kickoff is 12:00 a.m. New York time.</li>
                <li>{{ $challenge->modeLabel() }}.</li>
                @if ($challenge->sundays_off)<li>Sundays off.</li>@endif
                <li>Ranked by completion, never stars. It doesn't change any GoalLeague Standings.</li>
            </ul>
        </div>

        @if ($blocker)
            <p class="rounded-xl bg-ivory px-3 py-3 text-[15px] font-semibold text-ink" data-join-blocked>{{ $blocker }}</p>
            <a href="{{ route('challenges.create') }}" class="inline-flex min-h-11 items-center font-semibold text-gold-ink hover:underline">Start your own Challenge</a>
        @else
            <form method="POST" action="{{ route('challenges.join.store', $challenge->invite_code) }}" data-live="false" class="space-y-3">
                @csrf
                <label class="flex min-h-11 items-center gap-2.5 text-[15px]">
                    <input type="checkbox" name="agree" value="1" required class="size-5 accent-[#85651c]"> I&rsquo;m in for these rules
                </label>
                @error('agree') <p class="text-[13px] text-red-700">{{ $message }}</p> @enderror
                <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-full bg-gold px-6 text-[16px] font-bold text-ink hover:brightness-105">Join the Challenge</button>
            </form>
        @endif
    </section>
</x-layouts.app>
