<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use App\Services\AdminStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminController extends Controller
{
    /**
     * The admin page: beta invites, who's using GoalLine and how, and AI spend against the limits.
     */
    public function index(AdminStats $stats): View
    {
        return view('admin.index', [
            'members' => $stats->members(),
            'active' => $stats->activeMembers(),
            'dailyActive' => $stats->dailyActive(),
            'features' => $stats->featureUse(),
            'ai' => $stats->aiSpend(),
            'invites' => Invite::withCount('members')->latest()->get(),
            'inviteOnly' => config('goalline.beta.invite_only'),
            'feedback' => DB::table('feedback')
                ->leftJoin('users', 'users.id', '=', 'feedback.user_id')
                ->select('feedback.*', 'users.name as member')
                ->orderByRaw('feedback.resolved_at is not null')
                ->latest('feedback.created_at')
                ->limit(50)
                ->get(),
        ]);
    }

    /**
     * Make an invite code: one person, or a small group sharing a code.
     */
    public function storeInvite(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:120'],
            'max_uses' => ['required', 'integer', 'between:1,50'],
        ]);

        $invite = new Invite(['note' => $validated['note'] ?? null, 'max_uses' => $validated['max_uses']]);
        $invite->forceFill(['created_by' => $request->user()->id])->save();

        return redirect()->to(route('admin.index').'#invites')->with('status', "Invite {$invite->code} made. Send them the link.");
    }

    /**
     * Mark a beta tester's note as handled.
     */
    public function resolveFeedback(int $feedback): RedirectResponse
    {
        DB::table('feedback')->where('id', $feedback)->update(['resolved_at' => now(), 'updated_at' => now()]);

        return redirect()->to(route('admin.index').'#feedback');
    }

    /**
     * Stop a code from being used again. People who already joined keep their accounts.
     */
    public function revokeInvite(Invite $invite): RedirectResponse
    {
        $invite->forceFill(['revoked_at' => now()])->save();

        return redirect()->to(route('admin.index').'#invites')->with('status', "Invite {$invite->code} revoked.");
    }
}
