<?php

namespace App\Http\Controllers;

use App\Exceptions\AiLimitReached;
use App\HighlightKind;
use App\Models\Highlight;
use App\Models\RoadmapTask;
use App\Notifications\ProofRequested;
use App\PostVisibility;
use App\Services\CaptionWriter;
use App\Services\FairPlay;
use App\Services\ProofImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TaskProofController extends Controller
{
    /**
     * "Show your work": add a photo or screenshot to a checked-off task. Location and camera data are removed.
     */
    public function store(Request $request, RoadmapTask $task, ProofImage $images): RedirectResponse
    {
        $week = $task->week;
        Gate::authorize('update', $week->goal);
        abort_unless($task->completed_at && $week->isCheckable(), 403, 'Proof can be added to a checked-off task while its GoalWeek is open.');

        $validated = $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
            'visibility' => ['nullable', Rule::enum(PostVisibility::class)],
            'league_id' => ['nullable', 'integer'],
            'caption' => ['nullable', 'string', 'max:'.Highlight::MAX_POST_CAPTION],
            'location' => ['nullable', 'string', 'max:'.Highlight::MAX_LOCATION],
        ], [
            'caption.max' => 'Captions can be up to '.Highlight::MAX_POST_CAPTION.' characters.',
            'location.max' => 'Keep "Where?" to '.Highlight::MAX_LOCATION.' characters.',
            'proof.required' => 'Choose a photo or screenshot first.',
            'proof.mimes' => 'Use a JPG, PNG, or WebP image.',
            'proof.mimetypes' => 'Use a JPG, PNG, or WebP image.',
            'proof.max' => 'Photos can be up to 5 MB.',
        ]);

        try {
            $clean = $images->clean($request->file('proof'));
        } catch (Throwable) {
            return back()->withErrors(['proof' => "That file doesn't look like a JPG, PNG, or WebP image."])->withInput();
        }

        $disk = Storage::disk(RoadmapTask::proofDisk());
        $path = "proofs/{$week->goal_id}/{$task->id}-".Str::random(12).'.'.$clean['extension'];
        $disk->put($path, $clean['bytes']);

        if ($task->proof_path) {
            $disk->delete($task->proof_path);
        }

        $task->forceFill(['proof_path' => $path, 'proof_uploaded_at' => now()])->save();

        $post = $this->share($task, $clean, $validated);

        return $this->backTo($request, $task)->with('status', match (true) {
            $post?->league !== null => "Proof added and posted to {$post->league->name}. That check-in is Verified.",
            $post !== null => 'Proof added and posted for your friends. That check-in is Verified.',
            default => 'Proof added. That check-in is Verified.',
        });
    }

    /**
     * The proof photo, for the member, their matchup opponent, and the league owner only.
     */
    public function show(Request $request, RoadmapTask $task, FairPlay $fairPlay): StreamedResponse
    {
        abort_unless($task->proof_path && $fairPlay->canSeeProof($request->user(), $task), 404);

        return Storage::disk(RoadmapTask::proofDisk())->response($task->proof_path, headers: [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Ask a league mate to show the work behind a check-in. Friendly, once per person, and it never changes a score.
     */
    public function request(Request $request, RoadmapTask $task, FairPlay $fairPlay): RedirectResponse
    {
        abort_unless($fairPlay->canRequestProof($request->user(), $task), 403);

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
    private function share(RoadmapTask $task, array $clean, array $validated): ?Highlight
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

        $disk = Storage::disk(Highlight::PHOTO_DISK);
        $photoPath = "highlights/{$post->id}/post-".Str::random(12).'.'.$clean['extension'];
        $disk->put($photoPath, $clean['bytes']);

        if ($post->photo_path) {
            $disk->delete($post->photo_path);
        }

        $post->forceFill(['photo_path' => $photoPath])->save();

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
            default => redirect()->to(route('goals.show', $task->week->goal).'#goalweek-'.$task->week->position),
        };
    }
}
