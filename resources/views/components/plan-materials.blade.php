@props(['goal'])

@php
    $materials = $goal->materials;
    $canEdit = $goal->isActive();
    $reading = $materials->contains(fn ($material) => $material->status === App\RoadmapStatus::Pending);
    $newSinceRoadmap = $goal->roadmap_status === App\RoadmapStatus::Ready
        && $goal->roadmap_generated_at
        && $goal->readMaterials()->contains(fn ($material) => $material->updated_at->gt($goal->roadmap_generated_at));
@endphp

@if ($reading)
    @push('head')
        <meta http-equiv="refresh" content="8">
    @endpush
@endif

@if ($materials->isNotEmpty() || $canEdit)
    <section id="materials" class="mt-6 scroll-mt-6 rounded-2xl border border-line bg-white px-6 py-5">
        <div class="flex items-center justify-between gap-4">
            <h2 class="eyebrow text-club">Your plan materials</h2>
            @if ($materials->isEmpty())
                <span class="text-sm text-ink/50">Optional</span>
            @endif
        </div>

        @if ($materials->isEmpty())
            <p class="mt-2 text-ink/70">
                Following a course, study plan, training program, or meal plan? Upload it and GoalLine will build your roadmap around it.
            </p>
        @else
            <ul class="mt-3 divide-y divide-line">
                @foreach ($materials as $material)
                    <li class="py-3">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-ink">{{ $material->digest['title'] ?? $material->original_name }}</p>
                                <p class="text-sm text-ink/55">
                                    {{ $material->original_name }}
                                    @if ($material->digest['kind'] ?? null)
                                        &middot; {{ $material->digest['kind'] }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-3">
                                @if ($material->status === App\RoadmapStatus::Pending)
                                    <span class="flex items-center gap-2 text-sm text-ink/60"><span class="size-2 animate-pulse rounded-full bg-grass" aria-hidden="true"></span>Reading&hellip;</span>
                                @elseif ($material->status === App\RoadmapStatus::Failed)
                                    <span class="text-sm text-red-700">Couldn't read this file</span>
                                @elseif (! ($material->digest['relevant'] ?? true))
                                    <span class="text-sm text-amber-700">Doesn't look like a plan for this goal</span>
                                @else
                                    <span class="eyebrow rounded-full bg-mint px-2.5 py-1 text-[0.65rem] text-club">Read</span>
                                @endif

                                @if ($canEdit)
                                    <form method="POST" action="{{ route('materials.destroy', $material) }}" onsubmit="return confirm('Remove this file?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-semibold text-ink/50 hover:text-red-700">Remove</button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        @if ($material->status === App\RoadmapStatus::Ready && $material->digest)
                            <details class="mt-2">
                                <summary class="cursor-pointer text-sm font-semibold text-club">What GoalLine took from it</summary>
                                <div class="mt-2 space-y-2 rounded-xl bg-ivory px-4 py-3 text-sm text-ink/80">
                                    <p>{{ $material->digest['summary'] }}</p>
                                    <p class="whitespace-pre-line"><span class="font-semibold text-ink">Structure:</span> {{ $material->digest['structure'] }}</p>
                                    <p><span class="font-semibold text-ink">What it asks of you:</span> {{ $material->digest['requirements'] }}</p>
                                </div>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($canEdit && $newSinceRoadmap)
            <div class="mt-4 flex flex-col gap-3 rounded-xl border border-grass/40 bg-mint px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-club">Your roadmap was built before this file. Re-plan to build it around your materials.</p>
                <form method="POST" action="{{ route('goals.roadmap.store', $goal) }}">
                    @csrf
                    <button type="submit" class="w-full rounded-lg bg-club px-4 py-2 text-sm font-semibold text-ivory hover:bg-club-dark sm:w-auto">
                        {{ $goal->replanFromPosition() > 1 ? 'Re-plan from here' : 'Rebuild roadmap' }}
                    </button>
                </form>
            </div>
        @endif

        @if ($canEdit && $materials->count() < App\Models\PlanMaterial::MAX_PER_GOAL)
            <details class="mt-4" @if ($errors->has('material') || $errors->has('material_note')) open @endif>
                <summary class="cursor-pointer text-sm font-semibold text-club">+ Upload a plan, course, or program</summary>
                <form method="POST" action="{{ route('goals.materials.store', $goal) }}" enctype="multipart/form-data" class="mt-3 space-y-3">
                    @csrf
                    <x-material-upload-fields />
                    <button type="submit" class="rounded-lg bg-club px-4 py-2 text-sm font-semibold text-ivory hover:bg-club-dark">Upload</button>
                </form>
            </details>
        @endif
    </section>
@endif
