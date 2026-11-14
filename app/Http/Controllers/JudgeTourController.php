<?php

namespace App\Http\Controllers;

use App\Services\JudgeTour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The Judge's tour in demo accounts: "Take me there", the page's own pings for things seen (the AI panel opened), and
 * hiding or showing the tour. Anyone who isn't in a demo gets a 404.
 */
class JudgeTourController extends Controller
{
    public function go(Request $request, string $item): RedirectResponse
    {
        abort_unless(JudgeTour::applies($request->user()) && isset(JudgeTour::ITEMS[$item]), 404);

        return redirect()->to(JudgeTour::destination($request->user(), $item));
    }

    public function seen(Request $request, string $item): JsonResponse
    {
        abort_unless(JudgeTour::applies($request->user()) && in_array($item, JudgeTour::PINGS, true), 404);

        JudgeTour::mark($request->user(), $item);

        return response()->json(['done' => count(JudgeTour::done($request->user())), 'of' => count(JudgeTour::ITEMS)]);
    }

    public function hide(Request $request): RedirectResponse|Response
    {
        abort_unless(JudgeTour::applies($request->user()), 404);

        JudgeTour::setHidden($request->user(), ! $request->boolean('show'));

        return $request->ajax() ? response()->noContent() : back();
    }
}
