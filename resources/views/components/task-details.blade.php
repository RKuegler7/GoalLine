{{-- Under a task: its number (plan and result), points, a Verified check when there's proof, the box to log a number,
     and "Show your work". The proof prompt with its benefit copy opens right after the task is checked off. --}}
@props(['task', 'checkable' => true, 'return' => null, 'context' => 'card'])

@php
    $done = (bool) $task->completed_at;
    $mine = $task->week->goal->user_id === auth()->id();
    $confirm = session('confirm_result');
    $confirming = $confirm && (int) $confirm['task'] === $task->id;
    $hasError = $errors->has('result') && (int) old('task_id') === $task->id;
    $justChecked = (int) session('proof_prompt') === $task->id;
    $proofError = $errors->has('proof') && (int) old('proof_task_id') === $task->id;
@endphp

<div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-ink/55">
    @if ($task->result_required)
        <span class="tabular-nums">
            @if ($done && $task->result_value !== null)
                <span class="font-semibold text-ink">{{ $task->formatNumber($task->result_value) }}</span> of {{ $task->formatNumber($task->result_target) }}
            @else
                Plan: {{ $task->formatNumber($task->result_target) }}
            @endif
        </span>
    @endif
    <span class="tabular-nums">{{ $done ? App\Points::format($task->earnedPoints()) : $task->points }} {{ Str::plural('pt', $done ? (int) ceil($task->earnedPoints()) : $task->points) }}</span>
    @if ($task->isVerified())
        <a href="{{ route('roadmap.tasks.proof', $task) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-full bg-mint px-2 py-0.5 font-semibold text-club hover:bg-grass/20" title="See the proof">
            <svg viewBox="0 0 16 16" class="size-3" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7" /></svg>
            Verified
        </a>
    @elseif ($done && $mine && $checkable && ! $justChecked)
        <a href="#proof-{{ $context }}-{{ $task->id }}" data-open-proof="proof-{{ $context }}-{{ $task->id }}" class="font-semibold text-club hover:underline">Show your work (optional)</a>
    @endif
</div>

{{-- Log a number. --}}
@if ($checkable && $mine && ! $done && $task->result_required)
    <div class="mt-1.5">
        @if ($confirming)
            <div class="rounded-xl border border-gold/60 bg-gold/15 px-3 py-2 text-sm">
                <p class="font-semibold text-ink">Are you sure?</p>
                <p class="text-ink/75">{{ $confirm['reason'] }} Big days happen. Just making sure it's not a typo.</p>
                <form method="POST" action="{{ route('roadmap.tasks.check', $task) }}" class="mt-2">
                    @csrf
                    <input type="hidden" name="task_id" value="{{ $task->id }}">
                    <input type="hidden" name="result" value="{{ $confirm['value'] }}">
                    <input type="hidden" name="confirmed" value="1">
                    @if ($return)
                        <input type="hidden" name="return" value="{{ $return }}">
                    @endif
                    <button type="submit" class="rounded-full bg-club px-3 py-1 text-xs font-semibold text-ivory hover:bg-club-dark">Yes, log {{ $task->formatNumber((float) $confirm['value']) }}</button>
                    <span class="ml-1 text-xs text-ink/55">or change the number below</span>
                </form>
            </div>
        @endif
        <form method="POST" action="{{ route('roadmap.tasks.check', $task) }}" class="mt-1.5 flex flex-wrap items-center gap-2">
            @csrf
            <input type="hidden" name="task_id" value="{{ $task->id }}">
            @if ($return)
                <input type="hidden" name="return" value="{{ $return }}">
            @endif
            <label for="result-{{ $context }}-{{ $task->id }}" class="sr-only">How much did you do, in {{ $task->result_unit }}?</label>
            <input id="result-{{ $context }}-{{ $task->id }}" name="result" type="number" inputmode="decimal" step="any" min="{{ $task->result_min }}" max="{{ $task->result_max }}" required
                value="{{ $confirming ? $confirm['value'] : ((int) old('task_id') === $task->id ? old('result') : '') }}"
                placeholder="{{ rtrim(rtrim(number_format($task->result_target, 2, '.', ''), '0'), '.') }}"
                class="w-24 rounded-lg border border-line bg-white px-2.5 py-1 text-sm tabular-nums outline-none focus:border-club focus:ring-4 focus:ring-club/10">
            <span class="text-xs text-ink/55">{{ $task->result_unit }}</span>
            <button type="submit" class="rounded-full bg-club px-3 py-1 text-xs font-semibold text-ivory hover:bg-club-dark">Log it</button>
        </form>
        @if ($hasError)
            <p class="mt-1 text-xs text-red-700">{{ $errors->first('result') }}</p>
        @endif
    </div>
@endif

{{-- Show your work: opens right after checking off, or from the link above. --}}
@if ($done && $mine && $checkable && ! $task->isVerified())
    <div id="proof-{{ $context }}-{{ $task->id }}" data-proof-panel @if (! $justChecked && ! $proofError) hidden @endif
        class="mt-2 rounded-xl border border-club/25 bg-mint/50 px-3 py-2.5 text-sm">
        <p class="font-semibold text-club">Show your work (optional)</p>
        <p class="mt-0.5 text-xs text-ink/70">Add a photo or screenshot. It earns a Verified check, shows your league the process behind your goal, earns kudos, and keeps scoring fair. A Smashed week needs proof or your opponent's OK to count in full.</p>
        <form method="POST" action="{{ route('roadmap.tasks.proof.store', $task) }}" enctype="multipart/form-data" class="mt-2 flex flex-wrap items-center gap-2">
            @csrf
            <input type="hidden" name="proof_task_id" value="{{ $task->id }}">
            @if ($return)
                <input type="hidden" name="return" value="{{ $return }}">
            @endif
            <label for="proof-file-{{ $context }}-{{ $task->id }}" class="sr-only">Photo or screenshot</label>
            <input id="proof-file-{{ $context }}-{{ $task->id }}" name="proof" type="file" accept="image/jpeg,image/png,image/webp" required
                class="min-w-0 flex-1 text-xs text-ink/70 file:mr-3 file:rounded-full file:border-0 file:bg-club file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-ivory hover:file:bg-club-dark">
            <button type="submit" class="rounded-full border border-club/40 px-3 py-1 text-xs font-semibold text-club hover:border-club">Add proof</button>
        </form>
        <p class="mt-1 text-[0.7rem] text-ink/50">JPG, PNG, or WebP, up to 5 MB. Location data is removed.</p>
        @if ($proofError)
            <p class="mt-1 text-xs text-red-700">{{ $errors->first('proof') }}</p>
        @endif
    </div>
@endif
