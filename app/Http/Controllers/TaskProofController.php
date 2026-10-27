<?php

namespace App\Http\Controllers;

use App\Models\RoadmapTask;
use App\Notifications\ProofRequested;
use App\Services\FairPlay;
use App\Services\ProofImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
        ], [
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

        return $this->backTo($request, $task)->with('status', 'Proof added. That check-in is Verified.');
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

    private function backTo(Request $request, RoadmapTask $task): RedirectResponse
    {
        return match ($request->input('return')) {
            'clubhouse' => redirect()->to(route('dashboard').'#goal-'.$task->week->goal_id),
            'gameplan' => redirect()->to(route('dashboard').'#gameplan'),
            default => redirect()->to(route('goals.show', $task->week->goal).'#goalweek-'.$task->week->position),
        };
    }
}
