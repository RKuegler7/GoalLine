<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use App\Services\AdminStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * Stop a code from being used again. People who already joined keep their accounts.
     */
    public function revokeInvite(Invite $invite): RedirectResponse
    {
        $invite->forceFill(['revoked_at' => now()])->save();

        return redirect()->to(route('admin.index').'#invites')->with('status', "Invite {$invite->code} revoked.");
    }
}
