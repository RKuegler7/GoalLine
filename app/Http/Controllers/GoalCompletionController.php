<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GoalCompletionController extends Controller
{
    /**
     * Ask the person to confirm they crossed the line, with an optional reflection and photo.
     */
    public function create(Goal $goal): View
    {
        return view('goals.complete', ['goal' => $goal->load('weeks.tasks', 'habits.logs')]);
    }

    /**
     * Mark the goal complete.
     */
    public function store(Request $request, Goal $goal): RedirectResponse
    {
        $validated = $request->validate([
            'completion_note' => ['nullable', 'string', 'max:1000'],
            'completion_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'completion_photo.max' => 'Photos can be up to 5 MB.',
        ]);

        $photoPath = $request->hasFile('completion_photo')
            ? $request->file('completion_photo')->store("goal-photos/{$goal->id}", Goal::PHOTO_DISK)
            : null;

        $goal->complete($validated['completion_note'] ?? null, $photoPath);

        return redirect()->route('goals.show', $goal);
    }

    /**
     * Serve the photo from a completed goal.
     */
    public function photo(Goal $goal): StreamedResponse
    {
        // Completion photos show in the trophy case, so anyone who can see the owner's profile can see them.
        abort_unless(Gate::allows('view', $goal) || ($goal->isCompleted() && Gate::allows('viewProfile', $goal->user)), 403);
        abort_unless($goal->completion_photo_path, 404);

        return Storage::disk(Goal::PHOTO_DISK)->response($goal->completion_photo_path, headers: [
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }
}
