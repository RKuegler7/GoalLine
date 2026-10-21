<?php

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\CoachCheckController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\RoadmapController;
use App\Http\Controllers\RoadmapTaskController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

    Route::get('/goals/create', [GoalController::class, 'create'])->name('goals.create');
    Route::post('/goals', [GoalController::class, 'store'])->name('goals.store');
    Route::get('/goals/{goal}', [GoalController::class, 'show'])->name('goals.show')->can('view', 'goal');
    Route::get('/goals/{goal}/edit', [GoalController::class, 'edit'])->name('goals.edit')->can('update', 'goal');
    Route::put('/goals/{goal}', [GoalController::class, 'update'])->name('goals.update')->can('update', 'goal');
    Route::get('/goals/{goal}/leave', [GoalController::class, 'leave'])->name('goals.leave')->can('update', 'goal');
    Route::post('/goals/{goal}/retire', [GoalController::class, 'retire'])->name('goals.retire')->can('retire', 'goal');
    Route::delete('/goals/{goal}', [GoalController::class, 'destroy'])->name('goals.destroy')->can('delete', 'goal');

    Route::post('/goals/{goal}/roadmap', [RoadmapController::class, 'store'])
        ->name('goals.roadmap.store')
        ->can('update', 'goal')
        ->middleware('throttle:5,10');

    Route::post('/goals/{goal}/coach-check/fix', [CoachCheckController::class, 'applyFix'])->name('goals.coach.fix')->can('update', 'goal');
    Route::post('/goals/{goal}/coach-check/dismiss', [CoachCheckController::class, 'dismiss'])->name('goals.coach.dismiss')->can('update', 'goal');

    Route::post('/goalweeks/{week}/tasks', [RoadmapTaskController::class, 'store'])->name('roadmap.tasks.store');
    Route::put('/roadmap-tasks/{task}', [RoadmapTaskController::class, 'update'])->name('roadmap.tasks.update');
    Route::delete('/roadmap-tasks/{task}', [RoadmapTaskController::class, 'destroy'])->name('roadmap.tasks.destroy');
});
