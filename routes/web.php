<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\CoachCheckController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\GameplanController;
use App\Http\Controllers\GoalCompletionController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\HabitController;
use App\Http\Controllers\HighlightCommentController;
use App\Http\Controllers\HighlightController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\LeagueController;
use App\Http\Controllers\LeagueMembershipController;
use App\Http\Controllers\LeagueMessageController;
use App\Http\Controllers\LeagueRecapController;
use App\Http\Controllers\NotificationSettingsController;
use App\Http\Controllers\NudgeController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\PlanMaterialController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RoadmapController;
use App\Http\Controllers\RoadmapTaskController;
use App\Http\Controllers\WeeklyRecapController;
use App\Http\Controllers\WeekReviewController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/terms', 'legal.terms')->name('terms');

// Calendar apps fetch this without logging in; the secret token in the link is the key.
Route::get('/calendar/{token}.ics', [CalendarController::class, 'feed'])->name('calendar.feed')->middleware('throttle:60,1');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:5,1');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email')->middleware('throttle:5,1');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store')->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/welcome', [WelcomeController::class, 'you'])->name('welcome.show');
    Route::put('/welcome', [WelcomeController::class, 'saveYou'])->name('welcome.you');
    Route::get('/welcome/goal', [WelcomeController::class, 'goal'])->name('welcome.goal');
    Route::get('/welcome/crew', [WelcomeController::class, 'crew'])->name('welcome.crew');
    Route::get('/welcome/rules', [WelcomeController::class, 'rules'])->name('welcome.rules');
    Route::post('/welcome/finish', [WelcomeController::class, 'finish'])->name('welcome.finish');

    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->name('verification.verify')->middleware(['signed', 'throttle:6,1']);
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])->name('verification.send')->middleware('throttle:6,1');

    Route::get('/account/delete', [AccountController::class, 'confirmDelete'])->name('account.delete');
    Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/avatars/{user}', [ProfileController::class, 'avatar'])->name('avatars.show');
    Route::get('/covers/{user}', [ProfileController::class, 'cover'])->name('covers.show');
    Route::get('/u/{user}', [ProfileController::class, 'showUser'])->name('profiles.show');

    Route::get('/highlights', [HighlightController::class, 'index'])->name('highlights.index');
    Route::get('/highlights/new', [HighlightController::class, 'create'])->name('highlights.create');
    Route::post('/highlights', [HighlightController::class, 'store'])->name('highlights.store')->middleware(['verified', 'throttle:20,60']);
    Route::delete('/highlights/{highlight}', [HighlightController::class, 'destroy'])->name('highlights.destroy')->can('delete', 'highlight');
    Route::post('/highlights/{highlight}/cheer', [HighlightController::class, 'cheer'])->name('highlights.cheer')->can('view', 'highlight')->middleware(['verified', 'throttle:60,1']);
    Route::post('/highlights/{highlight}/comments', [HighlightCommentController::class, 'store'])->name('highlights.comments.store')->can('view', 'highlight')->middleware(['verified', 'throttle:30,1']);
    Route::delete('/comments/{comment}', [HighlightCommentController::class, 'destroy'])->name('highlights.comments.destroy');
    Route::get('/highlights/{highlight}/photo', [HighlightController::class, 'photo'])->name('highlights.photo')->can('view', 'highlight');
    Route::get('/highlights/{highlight}/card', [HighlightController::class, 'card'])->name('highlights.card')->can('delete', 'highlight');

    Route::get('/people', [PeopleController::class, 'index'])->name('people.index');
    Route::post('/u/{user}/follow', [FollowController::class, 'store'])->name('follows.store')->middleware('verified');
    Route::delete('/u/{user}/follow', [FollowController::class, 'destroy'])->name('follows.destroy');

    Route::get('/goals/create', [GoalController::class, 'create'])->name('goals.create');
    Route::post('/goals', [GoalController::class, 'store'])->name('goals.store');
    Route::get('/goals/{goal}', [GoalController::class, 'show'])->name('goals.show')->can('view', 'goal');
    Route::get('/goals/{goal}/edit', [GoalController::class, 'edit'])->name('goals.edit')->can('update', 'goal');
    Route::put('/goals/{goal}', [GoalController::class, 'update'])->name('goals.update')->can('update', 'goal');
    Route::get('/goals/{goal}/leave', [GoalController::class, 'leave'])->name('goals.leave')->can('update', 'goal');
    Route::post('/goals/{goal}/retire', [GoalController::class, 'retire'])->name('goals.retire')->can('retire', 'goal');
    Route::get('/goals/{goal}/complete', [GoalCompletionController::class, 'create'])->name('goals.complete')->can('update', 'goal');
    Route::post('/goals/{goal}/complete', [GoalCompletionController::class, 'store'])->name('goals.complete.store')->can('update', 'goal');
    Route::get('/goals/{goal}/photo', [GoalCompletionController::class, 'photo'])->name('goals.photo');
    Route::delete('/goals/{goal}', [GoalController::class, 'destroy'])->name('goals.destroy')->can('delete', 'goal');

    Route::post('/goals/{goal}/roadmap', [RoadmapController::class, 'store'])
        ->name('goals.roadmap.store')
        ->can('update', 'goal')
        ->middleware('throttle:5,10');

    Route::post('/goals/{goal}/coach-check/fix', [CoachCheckController::class, 'applyFix'])->name('goals.coach.fix')->can('update', 'goal');
    Route::post('/goals/{goal}/coach-check/dismiss', [CoachCheckController::class, 'dismiss'])->name('goals.coach.dismiss')->can('update', 'goal');

    Route::post('/goals/{goal}/materials', [PlanMaterialController::class, 'store'])
        ->name('goals.materials.store')
        ->can('update', 'goal')
        ->middleware('throttle:10,10');
    Route::delete('/plan-materials/{material}', [PlanMaterialController::class, 'destroy'])->name('materials.destroy');

    Route::post('/roadmap-tasks/{task}/check', [CheckInController::class, 'toggleTask'])->name('roadmap.tasks.check');
    Route::post('/goalweeks/{week}/milestone', [CheckInController::class, 'toggleMilestone'])->name('goalweeks.milestone');
    Route::get('/goalweeks/{week}/review', [WeekReviewController::class, 'show'])->name('goalweeks.review');
    Route::post('/goalweeks/{week}/review', [WeekReviewController::class, 'store'])->name('goalweeks.review.store')->middleware('throttle:10,1');

    Route::get('/inbox', InboxController::class)->name('inbox');
    Route::get('/my-week', WeeklyRecapController::class)->name('recap.week');

    Route::middleware('can:admin')->prefix('admin')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('admin.index');
        Route::post('/invites', [AdminController::class, 'storeInvite'])->name('admin.invites.store');
        Route::delete('/invites/{invite}', [AdminController::class, 'revokeInvite'])->name('admin.invites.revoke');
    });

    Route::get('/settings/notifications', [NotificationSettingsController::class, 'edit'])->name('settings.notifications');
    Route::put('/settings/notifications', [NotificationSettingsController::class, 'update'])->name('settings.notifications.update');
    Route::post('/settings/calendar/reset', [CalendarController::class, 'reset'])->name('calendar.reset');
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::post('/u/{user}/nudge', [NudgeController::class, 'store'])->name('nudges.store')->middleware(['verified', 'throttle:30,1']);
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');

    Route::get('/plan', [GameplanController::class, 'show'])->name('planner.show');
    Route::put('/plan', [GameplanController::class, 'update'])->name('planner.update');
    Route::post('/plan/auto', [GameplanController::class, 'autoPlan'])->name('planner.auto');
    Route::post('/roadmap-tasks/{task}/tomorrow', [GameplanController::class, 'moveToTomorrow'])->name('roadmap.tasks.tomorrow');

    Route::post('/goals/{goal}/habits', [HabitController::class, 'store'])->name('habits.store')->can('update', 'goal');
    Route::put('/habits/{habit}', [HabitController::class, 'update'])->name('habits.update');
    Route::delete('/habits/{habit}', [HabitController::class, 'destroy'])->name('habits.destroy');
    Route::post('/habits/{habit}/log', [HabitController::class, 'log'])->name('habits.log');

    Route::post('/goalweeks/{week}/tasks', [RoadmapTaskController::class, 'store'])->name('roadmap.tasks.store');
    Route::put('/roadmap-tasks/{task}', [RoadmapTaskController::class, 'update'])->name('roadmap.tasks.update');
    Route::delete('/roadmap-tasks/{task}', [RoadmapTaskController::class, 'destroy'])->name('roadmap.tasks.destroy');

    Route::get('/challenges/create', [ChallengeController::class, 'create'])->name('challenges.create');
    Route::post('/challenges', [ChallengeController::class, 'store'])->name('challenges.store')->middleware(['verified', 'throttle:10,60']);
    Route::post('/challenges/find', [ChallengeController::class, 'find'])->name('challenges.find')->middleware('throttle:20,1');
    Route::get('/challenges/join/{code}', [ChallengeController::class, 'join'])->name('challenges.join');
    Route::post('/challenges/join/{code}', [ChallengeController::class, 'joinStore'])->name('challenges.join.store')->middleware('verified');
    Route::get('/challenges/{challenge}', [ChallengeController::class, 'show'])->name('challenges.show')->can('view', 'challenge');
    Route::post('/challenges/{challenge}/check-in', [ChallengeController::class, 'checkIn'])->name('challenges.check-in')->can('view', 'challenge');
    Route::delete('/challenges/{challenge}/membership', [ChallengeController::class, 'leave'])->name('challenges.leave')->can('view', 'challenge');
    Route::delete('/challenges/{challenge}', [ChallengeController::class, 'destroy'])->name('challenges.destroy')->can('delete', 'challenge');

    Route::get('/leagues', [LeagueController::class, 'index'])->name('leagues.index');
    Route::get('/leagues/create', [LeagueController::class, 'create'])->name('leagues.create');
    Route::post('/leagues', [LeagueController::class, 'store'])->name('leagues.store')->middleware(['verified', 'throttle:10,60']);
    Route::get('/leagues/{league}', [LeagueController::class, 'show'])->name('leagues.show')->can('view', 'league');
    Route::get('/leagues/{league}/recap', LeagueRecapController::class)->name('leagues.recap')->can('view', 'league');
    Route::delete('/leagues/{league}', [LeagueController::class, 'destroy'])->name('leagues.destroy')->can('delete', 'league');

    Route::post('/leagues/find', [LeagueMembershipController::class, 'find'])->name('leagues.find')->middleware('throttle:20,1');
    Route::get('/join/{code}', [LeagueMembershipController::class, 'create'])->name('leagues.join');
    Route::post('/join/{code}', [LeagueMembershipController::class, 'store'])->name('leagues.join.store')->middleware('verified');
    Route::put('/leagues/{league}/membership', [LeagueMembershipController::class, 'update'])->name('leagues.membership.update')->can('view', 'league');
    Route::get('/leagues/{league}/messages', [LeagueMessageController::class, 'index'])->name('leagues.messages.index')->can('view', 'league');
    Route::post('/leagues/{league}/messages', [LeagueMessageController::class, 'store'])->name('leagues.messages.store')->can('view', 'league')->middleware(['verified', 'throttle:30,1']);
    Route::delete('/league-messages/{message}', [LeagueMessageController::class, 'destroy'])->name('leagues.messages.destroy');
    Route::delete('/leagues/{league}/membership', [LeagueMembershipController::class, 'destroy'])->name('leagues.membership.destroy');
});
