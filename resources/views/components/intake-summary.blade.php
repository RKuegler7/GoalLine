@props(['goal'])

{{-- What the plan is built from: the person's intake answers. Only the owner sees them; health answers stay private. --}}
@php
    $intake = $goal->intake;
    $given = $intake?->givenAnswers() ?? [];
@endphp

@can('update', $goal)
    @if ($goal->isActive() && $intake?->isOpen())
        <section class="mt-6 flex flex-col gap-4 rounded-2xl border-2 border-club bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-serif text-xl font-bold text-club tracking-tight">A few questions first</h2>
                <p class="mt-1 text-sm text-ink/70">Tell GoalLine where you're starting from, and your plan will fit you instead of everyone.</p>
            </div>
            <a href="{{ route('goals.intake', $goal) }}" class="shrink-0 rounded-full bg-club px-5 py-2.5 text-center text-sm font-semibold text-ivory hover:bg-club-dark">Answer the questions</a>
        </section>
    @elseif ($given)
        <details class="group mt-2 rounded-xl border border-line bg-white">
            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 px-3 [&::-webkit-details-marker]:hidden">
                <h2 class="eyebrow text-club">Your plan is built from</h2>
                <x-icon name="chevron-down" class="size-4 shrink-0 text-ink/70 transition group-open:rotate-180" />
            </summary>
            <div class="border-t border-line px-3 py-2">
            <div class="flex items-start justify-end gap-4">
                @if ($goal->isActive())
                    <a href="{{ route('goals.intake', $goal) }}" class="shrink-0 text-sm font-semibold text-club hover:underline">Edit answers</a>
                @endif
            </div>
            <dl class="mt-3 grid gap-x-6 gap-y-3 sm:grid-cols-2">
                @foreach (array_slice($given, 0, 6) as $answer)
                    <div>
                        <dt class="text-sm text-ink/70">{{ $answer['question'] }}</dt>
                        <dd class="font-medium text-ink">{{ $answer['answer'] }}</dd>
                    </div>
                @endforeach
            </dl>
            </div>
        </details>
    @elseif ($goal->isActive() && $goal->roadmap_status === App\RoadmapStatus::Ready)
        <p class="mt-6 text-sm text-ink/65">
            Want a plan that fits you better?
            <a href="{{ route('goals.intake', $goal) }}" class="font-semibold text-club hover:underline">Answer a few questions about where you're starting</a>
        </p>
    @endif
@endcan

@if ($goal->isPhysical() && $goal->isActive())
    <p class="mt-6 rounded-xl bg-mint px-4 py-3 text-sm text-ink/75">GoalLine plans are general guidance, not medical advice. Check with a doctor if you have health concerns.</p>
@endif
