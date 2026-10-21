<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the clubhouse with the user's active goals.
     */
    public function __invoke(Request $request): View
    {
        return view('dashboard', [
            'goals' => $request->user()->goals()->active()->orderBy('target_date')->get(),
        ]);
    }
}
