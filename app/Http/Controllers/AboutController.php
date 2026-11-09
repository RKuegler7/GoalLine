<?php

namespace App\Http\Controllers;

use App\Services\ScoringRules;
use Illuminate\View\View;

class AboutController extends Controller
{
    /**
     * About GoalLine: what it is, how to play, and the scoring rules, read from the same code that scores every week.
     */
    public function __invoke(ScoringRules $rules): View
    {
        return view('about', [
            'rules' => $rules,
            // Only an address chosen for the public; never falls back to a personal or sending address.
            'contactEmail' => config('goalline.contact_email') ?: null,
        ]);
    }
}
