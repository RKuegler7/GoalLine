<?php

namespace App\Http\Controllers;

use App\Atomic;
use App\Clock;
use App\Exceptions\AiLimitReached;
use App\HighlightKind;
use App\Models\Dispute;
use App\Models\Highlight;
use App\Models\RoadmapTask;
use App\Notifications\ProofRequested;
use App\PostVisibility;
use App\Services\CaptionWriter;
use App\Services\Disputes;
use App\Services\FairPlay;
use App\Services\ProofImage;
use App\Services\StoredPhoto;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TaskProofController extends Controller
{
    /**
     * "Show your work": add a photo or screenshot, details (a caption, where, when), or both, to a checked-off task.
     * Photos have location and camera data removed and earn the Verified check; details never do.
     */
    public function store(Request $request, RoadmapTask $task, ProofImage $images): RedirectResponse
    {
        $week = $task->week;
        Gate::authorize('update', $week->goal);
        // A disputed check-in can get proof even after its GoalWeek closes: proof is how a dispute gets settled.
        abort_unless($task->countsAsCheckIn() && ($week->isCheckable() || $task->dispute_status === RoadmapTask::DISPUTE_OPEN), 403, 'Proof can be added to a checked-off task while its GoalWeek is open.');

        $validated = $request->validate([
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
            'visibility' => ['nullable', Rule::enum(PostVisibility::class)],
            'league_id' => ['nullable', 'integer'],
            'caption' => ['nullable', 'string', 'max:'.Highlight::MAX_POST_CAPTION],
            'location' => ['nullable', 'string', 'max:'.Highlight::MAX_LOCATION],
            'window' => ['nullable', Rule::in([...array_keys(RoadmapTask::WINDOWS), 'exact'])],
            'start_time' => ['nullable', 'required_with:end_time', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_with:start_time', 'date_format:H:i'],
        ], [
            'caption.max' => 'Captions can be up to '.Highlight::MAX_POST_CAPTION.' characters.',
            'location.max' => 'Keep "Where?" to '.Highlight::MAX_LOCATION.' characters.',
            'proof.mimes' => 'Use a JPG, PNG, or WebP image.',
            'proof.mimetypes' => 'Use a JPG, PNG, or WebP image.',
            'proof.max' => 'Photos can be up to 5 MB.',
            'start_time.required_with' => 'Add a start time too.',
            'end_time.required_with' => 'Add an end time too.',
            'start_time.date_format' => 'Use a time like 6:30.',
            'end_time.date_format' => 'Use a time like 7:15.',
        ]);

        $window = $this->window($task, $validated);
        $caption = filled($validated['caption'] ?? null) ? trim($validated['caption']) : null;
        $location = filled($validated['location'] ?? null) ? trim($validated['location']) : null;
        $hasPhoto = $request->hasFile('proof');

        if (! $hasPhoto && ! $caption && ! $location && ! $window['window']) {
            return back()->withErrors(['proof' => 'Add a photo, or a few details: a caption, where, or when.'])->withInput();
        }

        $clean = null;

        if ($hasPhoto) {
            try {
                $clean = $images->clean($request->file('proof'));
            } catch (Throwable) {
                return back()->withErrors(['proof' => "That file doesn't look like a JPG, PNG, or WebP image."])->withInput();
            }
        }

        $visibility = PostVisibility::tryFrom($validated['visibility'] ?? '') ?? ($task->week->goal->postingLeagues()->isEmpty() ? PostVisibility::Proof : PostVisibility::League);

        // The details: saved with the check-in, seen by the same people as the proof.
        $task->forceFill([
            'proof_caption' => $caption,
            'proof_location' => $location,
            'proof_window' => $window['window'],
            'proof_starts_at' => $window['starts'],
            'proof_ends_at' => $window['ends'],
            'proof_visibility' => $visibility->value,
            'details_added_at' => ($caption || $location || $window['window']) ? now() : null,
        ])->save();

        if ($clean) {
            // The upload happens first, outside any transaction; the database write is short and locks the task row.
            $disk = Storage::disk(RoadmapTask::proofDisk());
            $path = "proofs/{$week->goal_id}/{$task->id}-".Str::random(12).'.'.$clean['extension'];
            $disk->put($path, $clean['bytes']);

            $replaced = Atomic::run(function () use ($task, $path) {
                $current = RoadmapTask::whereKey($task->id)->lockForUpdate()->first();
                $old = $current->proof_path;
                $current->forceFill(['proof_path' => $path, 'proof_mime_type' => StoredPhoto::typeFor($path), 'proof_uploaded_at' => now()])->save();

                return $old;
            });
            $task->refresh();

            // After the commit: remove the old photo, then settle any open disputes (each in its own short transaction).
            if ($replaced && $replaced !== $path) {
                $disk->delete($replaced);
            }

            app(Disputes::class)->settleWithProof($task);
        } else {
            // Details without a photo answer an open dispute the way an explanation does. They never earn Verified.
            $task->disputes()->where('status', Dispute::OPEN)->get()
                ->each(fn (Dispute $dispute) => app(Disputes::class)->resolve($dispute, 'note', $task->week->goal->user, trim(implode(' · ', array_filter([$caption, $task->detailsLine()])))));
        }

        $post = $this->share($task, $clean, $validated);
        $task->refresh();

        return $this->backTo($request, $task)->with('status', match (true) {
            $clean && $post?->league !== null => "Proof added and posted to {$post->league->name}. That check-in is Verified.",
            $clean && $post !== null => 'Proof added and posted for your friends. That check-in is Verified.',
            (bool) $clean => 'Proof added. That check-in is Verified.',
            $post !== null => 'Details added and posted. Add a photo any time to earn the Verified check.',
            default => 'Details added. Add a photo any time to earn the Verified check.',
        });
    }

    /**
     * The time window: a quick choice (morning, afternoon, evening) or an exact start and end on the check-in's day, in
     * Eastern time. The end is after the start, nothing is in the future, and it's at most 12 hours.
     *
     * @param  array<string, mixed>  $validated
     * @return array{window: ?string, starts: ?CarbonInterface, ends: ?CarbonInterface}
     */
    private function window(RoadmapTask $task, array $validated): array
    {
        $start = $validated['start_time'] ?? null;
        $end = $validated['end_time'] ?? null;

        if (! $start && ! $end) {
            $choice = $validated['window'] ?? null;

            return ['window' => $choice === 'exact' ? null : $choice, 'starts' => null, 'ends' => null];
        }

        $day = Clock::local($task->completed_at)->toDateString();
        $zone = Clock::timezone();
        $starts = CarbonImmutable::parse("{$day} {$start}", $zone);
        $ends = CarbonImmutable::parse("{$day} {$end}", $zone);

        $problem = match (true) {
            $ends->lte($starts) => 'The end time needs to be after the start time.',
            $ends->gt(now()) => 'That time hasn\'t happened yet.',
            $starts->diffInMinutes($ends) > RoadmapTask::MAX_WINDOW_HOURS * 60 => 'Keep it to '.RoadmapTask::MAX_WINDOW_HOURS.' hours or less.',
            default => null,
        };

        if ($problem) {
            throw ValidationException::withMessages(['end_time' => $problem]);
        }

        return ['window' => 'exact', 'starts' => $starts, 'ends' => $ends];
    }

    /**
     * The proof photo, for the member, their matchup opponent, and the league owner only.
     */
    public function show(Request $request, RoadmapTask $task, FairPlay $fairPlay, StoredPhoto $photos): Response
    {
        abort_unless($task->proof_path && $fairPlay->canSeeProof($request->user(), $task), 404);

        // A missing proof file shows a placeholder, but the reference stays: clearing it would quietly change
        // Verified, disputes and the score.
        return $photos->serve(RoadmapTask::proofDisk(), $task->proof_path, $task->proof_mime_type, ['Cache-Control' => 'private, max-age=3600']);
    }

    /**
     * Ask a league mate to show the work behind a check-in. Friendly, once per person, and it never changes a score.
     */
    public function request(Request $request, RoadmapTask $task, FairPlay $fairPlay): RedirectResponse
    {
        abort_unless($fairPlay->canRequestProof($request->user(), $task), 403);

        abort_unless($task->countsAsCheckIn(), 403, 'Only check-ins that count can be asked about.');
        $proofRequest = $task->proofRequests()->firstOrCreate(['requester_id' => $request->user()->id]);

        if ($proofRequest->wasRecentlyCreated && ! $task->isVerified()) {
            $task->week->goal->user->notify(new ProofRequested($task->withoutRelations(), $request->user()));
        }

        return back()->with('status', 'Asked them to show their work. Thanks for keeping it fair.');
    }

    /**
     * Post the photo, unless it's proof only. A task has at most one post; a new photo replaces it.
     *
     * @param  array{bytes: string, extension: string}  $clean
     * @param  array{visibility?: ?string, league_id?: ?int, caption?: ?string, location?: ?string}  $validated
     */
    private function share(RoadmapTask $task, ?array $clean, array $validated): ?Highlight
    {
        $goal = $task->week->goal;
        $leagues = $goal->postingLeagues();
        $visibility = PostVisibility::tryFrom($validated['visibility'] ?? '') ?? ($leagues->isEmpty() ? PostVisibility::Proof : PostVisibility::League);
        $existing = $task->post;

        if ($visibility === PostVisibility::Proof || ($visibility === PostVisibility::League && $leagues->isEmpty())) {
            $existing?->delete();

            return null;
        }

        $post = $existing ?? new Highlight;
        $post->forceFill(['user_id' => $goal->user_id])->fill([
            'kind' => HighlightKind::CheckIn,
            'goal_id' => $goal->id,
            'roadmap_week_id' => $task->week->id,
            'roadmap_task_id' => $task->id,
            'league_id' => ($leagues->firstWhere('id', (int) ($validated['league_id'] ?? 0)) ?? $leagues->first())?->id,
            'visibility' => $visibility,
            'headline' => $goal->title,
            'detail' => $task->description,
            'caption' => filled($validated['caption'] ?? null) ? trim($validated['caption']) : null,
            'location' => filled($validated['location'] ?? null) ? trim($validated['location']) : null,
        ])->save();

        if (! $clean) {
            return $post->load('league');
        }

        $disk = Storage::disk(Highlight::PHOTO_DISK);
        $photoPath = "highlights/{$post->id}/post-".Str::random(12).'.'.$clean['extension'];
        $disk->put($photoPath, $clean['bytes']);

        if ($post->photo_path) {
            $disk->delete($post->photo_path);
        }

        $post->forceFill(['photo_path' => $photoPath, 'photo_mime_type' => StoredPhoto::typeFor($photoPath)])->save();

        return $post->load('league');
    }

    /**
     * "Suggest a caption": a suggestion for the caption box, as JSON. It never posts, and any failure just says so.
     */
    public function caption(Request $request, RoadmapTask $task, CaptionWriter $writer): JsonResponse
    {
        Gate::authorize('update', $task->week->goal);
        abort_unless($task->completed_at, 403);

        try {
            $caption = $writer->suggest($request->user(), $task);
        } catch (AiLimitReached $limit) {
            return response()->json(['message' => $limit->getMessage().' Write your own!'], 429);
        } catch (Throwable $error) {
            report($error);

            return response()->json(['message' => "Couldn't suggest one right now. Write your own!"], 503);
        }

        return $caption
            ? response()->json(['caption' => $caption])
            : response()->json(['message' => "Couldn't suggest one right now. Write your own!"], 503);
    }

    private function backTo(Request $request, RoadmapTask $task): RedirectResponse
    {
        return match ($request->input('return')) {
            'clubhouse' => redirect()->to(route('dashboard').'#goal-'.$task->week->goal_id),
            'gameplan' => redirect()->to(route('dashboard').'#gameplan'),
            'today' => redirect()->route('today'),
            default => redirect()->to(route('goals.show', $task->week->goal).'#goalweek-'.$task->week->position),
        };
    }
}
