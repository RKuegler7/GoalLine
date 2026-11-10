<?php

namespace App\Http\Controllers;

use App\HighlightKind;
use App\Models\Highlight;
use App\Models\ResultCard;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\Notifications\FriendHighlight;
use App\PostVisibility;
use App\Services\ResultCardData;
use App\Services\ResultCards\ResultCardImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Weekly result cards: "Share your week" makes a card from the scoring code's numbers, which the owner can post to
 * Highlights, share or download, and (only if they choose) open to anyone with a link.
 */
class ResultCardController extends Controller
{
    /**
     * The share sheet for a GoalWeek: its card (made fresh if the result changed), and the ways to share it.
     */
    public function show(Request $request, RoadmapWeek $week): View
    {
        abort_unless($week->goal->user_id === $request->user()->id, 403);
        abort_unless($week->isStarted(), 404);

        $card = $this->cardFor($request->user(), $week);

        return view('cards.share', [
            'card' => $card,
            'week' => $week,
            'canMakePng' => ResultCardImage::canMakePng(),
            'feedSvg' => app(ResultCardImage::class)->svg($card->payload, 'feed'),
            'storySvg' => app(ResultCardImage::class)->svg($card->payload, 'story'),
            'inLeague' => $card->payload['league'] !== null,
            'posted' => $request->user()->highlights()->where('kind', HighlightKind::WeekCard)->where('roadmap_week_id', $week->id)->first(),
        ]);
    }

    /**
     * The card image for its owner.
     */
    public function image(Request $request, ResultCard $card, string $size, string $format): Response
    {
        abort_unless($card->user_id === $request->user()->id, 403);

        return $this->imageResponse($card, $size, $format, 'private, max-age=86400');
    }

    /**
     * Post the card to Highlights, with the usual audience choices.
     */
    public function post(Request $request, ResultCard $card): RedirectResponse
    {
        $user = $request->user();
        abort_unless($card->user_id === $user->id, 403);

        $inLeague = $card->payload['league'] !== null;
        $validated = $request->validate([
            'caption' => ['nullable', 'string', 'max:'.Highlight::MAX_CAPTION],
            'visibility' => ['required', Rule::in($inLeague ? [PostVisibility::League->value, PostVisibility::LeagueFriends->value] : [PostVisibility::LeagueFriends->value])],
        ]);

        $week = $card->week()->with('goal')->firstOrFail();
        $existing = $user->highlights()->where('kind', HighlightKind::WeekCard)->where('roadmap_week_id', $week->id)->first();

        if ($existing) {
            return redirect()->to(route('highlights.index').'#highlight-'.$existing->id)->with('status', 'You already posted this week’s card.');
        }

        $leagueId = $inLeague ? ResultCardData::membershipFor($week)?->league_id : null;

        $highlight = $user->highlights()->create([
            'kind' => HighlightKind::WeekCard,
            'goal_id' => $week->goal_id,
            'roadmap_week_id' => $week->id,
            'league_id' => $leagueId,
            'visibility' => $leagueId ? PostVisibility::from($validated['visibility']) : PostVisibility::LeagueFriends,
            'headline' => $card->payload['goal'],
            'detail' => ResultCardImage::describe($card->payload),
            'caption' => filled($validated['caption'] ?? null) ? trim($validated['caption']) : null,
        ]);

        // The card itself is the post's picture: a PNG where the server can draw one, the SVG otherwise.
        $format = ResultCardImage::canMakePng() ? 'png' : 'svg';
        $image = app(ResultCardImage::class)->render($card, 'feed', $format);
        $path = "highlights/{$highlight->id}/week-card.{$format}";
        Storage::disk(Highlight::PHOTO_DISK)->put($path, $image['body']);
        $highlight->forceFill(['photo_path' => $path, 'photo_mime_type' => $image['type']])->save();

        Notification::send(
            $user->friends()->filter(fn (User $friend) => Gate::forUser($friend)->allows('view', $highlight)),
            new FriendHighlight($highlight),
        );

        return redirect()->to(route('highlights.index').'#highlight-'.$highlight->id)->with('status', 'Posted to Highlights.');
    }

    /**
     * Turn on the public link (opt in).
     */
    public function publish(Request $request, ResultCard $card): RedirectResponse
    {
        $user = $request->user();
        abort_unless($card->user_id === $user->id, 403);

        if (! $user->public_cards) {
            return back()->with('status', 'Public card links are off in your profile settings.');
        }

        $card->forceFill(['public_at' => now(), 'revoked_at' => null])->save();

        return back()->with('status', 'Link ready. Anyone with it can see this card.');
    }

    /**
     * Turn the public link off. The token stops working for good; a new link gets a new token.
     */
    public function revoke(Request $request, ResultCard $card): RedirectResponse
    {
        abort_unless($card->user_id === $request->user()->id, 403);

        $card->forceFill(['revoked_at' => now()])->save();

        return back()->with('status', 'Link turned off. It no longer opens.');
    }

    /**
     * The public, read-only card page, with link previews. Only the owner's own summary.
     */
    public function public(string $token): View
    {
        $card = $this->publicCard($token);

        return view('cards.public', [
            'card' => $card,
            'svg' => app(ResultCardImage::class)->svg($card->payload, 'feed'),
            'canMakePng' => ResultCardImage::canMakePng(),
        ]);
    }

    /**
     * The public card's image, for link previews.
     */
    public function publicImage(string $token, string $size, string $format): Response
    {
        return $this->imageResponse($this->publicCard($token), $size, $format, 'public, max-age=3600');
    }

    /**
     * The newest card for this GoalWeek, or a new one if what it shows has changed since.
     */
    private function cardFor(User $user, RoadmapWeek $week): ResultCard
    {
        $payload = ResultCardData::for($week);
        $hash = ResultCard::hashOf($payload);

        return $user->resultCards()->where('roadmap_week_id', $week->id)->where('content_hash', $hash)->latest('id')->first()
            ?? $user->resultCards()->create(['roadmap_week_id' => $week->id, 'payload' => $payload]);
    }

    private function publicCard(string $token): ResultCard
    {
        $card = ResultCard::query()->where('token', $token)->with('user')->first();
        abort_unless($card !== null && strlen($token) >= 40 && $card->isPublic(), 404);

        return $card;
    }

    private function imageResponse(ResultCard $card, string $size, string $format, string $cache): Response
    {
        abort_unless(isset(ResultCardImage::SIZES[$size]) && in_array($format, ResultCardImage::FORMATS, true), 404);
        abort_if($format === 'png' && ! ResultCardImage::canMakePng(), 404);

        $image = app(ResultCardImage::class)->render($card, $size, $format);

        return response($image['body'], 200, [
            'Content-Type' => $image['type'],
            'Cache-Control' => $cache,
            'X-Content-Type-Options' => 'nosniff',
            // A card is a picture, never a page: nothing in it may run.
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
