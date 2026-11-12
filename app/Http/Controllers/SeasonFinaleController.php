<?php

namespace App\Http\Controllers;

use App\HighlightKind;
use App\Models\Goal;
use App\Models\GoalResult;
use App\Models\Highlight;
use App\PostVisibility;
use App\Services\GoalDay;
use App\Services\ResultCards\ResultCardImage;
use App\Services\ResultCards\SeasonCardImage;
use App\Services\SeasonFinale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The season finale for a goal: skippable cards (result, the two results, the replay, highlights, league news,
 * medals, share, next goal), replayable from History and the profile. Plus "Share your season".
 */
class SeasonFinaleController extends Controller
{
    public function show(Request $request, Goal $goal, GoalDay $goalDay): View|RedirectResponse
    {
        // Not answered yet and it's time: the Goal Day check-in is the first finale screen.
        if (! $goal->result && $goalDay->isDue($goal) && ! $request->boolean('skip')) {
            return redirect()->route('goal-day.show', $goal);
        }

        $finale = new SeasonFinale($goal);

        return view('finale.show', [
            'finale' => $finale,
            'goal' => $goal,
            'card' => SeasonCardImage::payload($finale),
            'canMakePng' => ResultCardImage::canMakePng(),
            'posted' => $request->user()->highlights()->where('kind', HighlightKind::SeasonCard)->where('goal_id', $goal->id)->first(),
            'preview' => false,
        ]);
    }

    /**
     * Demo: open the finished CPA season's finale. Elsewhere, History, where every finale lives.
     */
    public function preview(Request $request): RedirectResponse
    {
        $goal = $request->user()->isDemo()
            ? $request->user()->goals()->has('result')->whereHas('leagues', fn ($leagues) => $leagues->whereDate('ends_on', '<', now()))->latest('target_date')->first()
            : null;

        return $goal
            ? redirect()->route('finale.show', ['goal' => $goal, 'skip' => 1])
            : redirect()->route('dashboard', ['tab' => 'history']);
    }

    /**
     * The season card image for its owner.
     */
    public function image(Goal $goal, string $size, string $format): Response
    {
        return $this->imageResponse($goal, $size, $format, 'private, max-age=300');
    }

    /**
     * Post the season card to Highlights. Never automatic.
     */
    public function post(Request $request, Goal $goal): RedirectResponse
    {
        $user = $request->user();
        $finale = new SeasonFinale($goal);
        $leagueId = $finale->league?->id;

        $validated = $request->validate([
            'caption' => ['nullable', 'string', 'max:'.Highlight::MAX_CAPTION],
            'visibility' => ['required', Rule::in($leagueId ? [PostVisibility::League->value, PostVisibility::LeagueFriends->value] : [PostVisibility::LeagueFriends->value])],
        ]);

        $existing = $user->highlights()->where('kind', HighlightKind::SeasonCard)->where('goal_id', $goal->id)->first();

        if ($existing) {
            return redirect()->to(route('highlights.index').'#highlight-'.$existing->id)->with('status', 'You already posted your season card.');
        }

        $payload = SeasonCardImage::payload($finale);
        $highlight = $user->highlights()->create([
            'kind' => HighlightKind::SeasonCard,
            'goal_id' => $goal->id,
            'league_id' => $leagueId,
            'visibility' => PostVisibility::from($validated['visibility']),
            'headline' => $goal->title,
            'detail' => SeasonCardImage::describe($payload),
            'caption' => filled($validated['caption'] ?? null) ? trim($validated['caption']) : null,
        ]);

        $format = ResultCardImage::canMakePng() ? 'png' : 'svg';
        $image = app(SeasonCardImage::class)->render($payload, 'feed', $format);
        $path = "highlights/{$highlight->id}/season-card.{$format}";
        Storage::disk(Highlight::PHOTO_DISK)->put($path, $image['body']);
        $highlight->forceFill(['photo_path' => $path, 'photo_mime_type' => $image['type']])->save();

        return redirect()->to(route('highlights.index').'#highlight-'.$highlight->id)->with('status', 'Posted to Highlights.');
    }

    /**
     * Turn on the public link to the season card (opt in). Needs a Goal Day answer.
     */
    public function publish(Request $request, Goal $goal): RedirectResponse
    {
        $result = $goal->result;
        abort_unless($result, 404);

        if (! $request->user()->public_cards) {
            return back()->with('status', 'Public card links are off in your profile settings.');
        }

        $result->forceFill([
            'share_token' => $result->revoked_at || ! $result->share_token ? Str::random(48) : $result->share_token,
            'public_at' => now(),
            'revoked_at' => null,
        ])->save();

        return redirect()->to(route('finale.show', $goal).'#finale-share')->with('status', 'Link ready. Anyone with it can see your season card.');
    }

    /**
     * Turn the public link off for good. A new link gets a new token.
     */
    public function revoke(Goal $goal): RedirectResponse
    {
        $goal->result?->forceFill(['revoked_at' => now()])->save();

        return redirect()->to(route('finale.show', $goal).'#finale-share')->with('status', 'Link turned off. It no longer opens.');
    }

    /**
     * The public season card page, with link previews.
     */
    public function public(string $token): View
    {
        $result = $this->publicResult($token);
        $payload = SeasonCardImage::payload(new SeasonFinale($result->goal));

        return view('finale.public', [
            'token' => $token,
            'payload' => $payload,
            'svg' => app(SeasonCardImage::class)->render($payload, 'feed', 'svg')['body'],
            'canMakePng' => ResultCardImage::canMakePng(),
        ]);
    }

    public function publicImage(string $token, string $size, string $format): Response
    {
        return $this->imageResponse($this->publicResult($token)->goal, $size, $format, 'public, max-age=300');
    }

    private function publicResult(string $token): GoalResult
    {
        $result = GoalResult::where('share_token', $token)->with('goal.user')->first();
        abort_unless($result !== null && strlen($token) >= 40 && SeasonCardImage::isPublic($result), 404);

        return $result;
    }

    private function imageResponse(Goal $goal, string $size, string $format, string $cache): Response
    {
        abort_unless(isset(ResultCardImage::SIZES[$size]) && in_array($format, ResultCardImage::FORMATS, true), 404);
        abort_if($format === 'png' && ! ResultCardImage::canMakePng(), 404);

        $image = app(SeasonCardImage::class)->render(SeasonCardImage::payload(new SeasonFinale($goal)), $size, $format);

        return response($image['body'], 200, [
            'Content-Type' => $image['type'],
            'Cache-Control' => $cache,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
