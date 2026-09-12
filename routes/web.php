<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\FirebaseMessagingServiceWorkerController;
use App\Http\Controllers\GeoguessrController;
use App\Http\Controllers\GeoguessrLiveController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PubGolfChatMessageController;
use App\Http\Controllers\PubGolfChatReadController;
use App\Http\Controllers\PubGolfCrawlController;
use App\Http\Controllers\PubGolfCrawlJoinController;
use App\Http\Controllers\PubGolfCrawlLeaveController;
use App\Http\Controllers\PubGolfCrawlRejoinController;
use App\Http\Controllers\PubGolfCustomDrinkController;
use App\Http\Controllers\PubGolfDrinkLogController;
use App\Http\Controllers\PubGolfDrinkUndoController;
use App\Http\Controllers\PubGolfRecapController;
use App\Http\Controllers\WicketFineCompletionController;
use App\Http\Controllers\WicketFineController;
use App\Http\Controllers\WicketGroupController;
use App\Http\Controllers\WicketGroupLiveController;
use App\Http\Controllers\WicketGroupMemberController;
use App\Http\Controllers\WicketSipLogController;
use App\Http\Middleware\EnsurePubGolfCrawlParticipant;
use App\Http\Middleware\EnsureWicketGroupMember;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('/firebase-messaging-sw.js', FirebaseMessagingServiceWorkerController::class)
    ->name('push.service-worker');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/geoguessr', [GeoguessrController::class, 'index'])->name('geoguessr.index');
    Route::get('/geoguessr/live', GeoguessrLiveController::class)->name('geoguessr.live');
    Route::get('/pub-golf', [PubGolfCrawlController::class, 'index'])->name('pub-golf.index');
    Route::post('/pub-golf', [PubGolfCrawlController::class, 'store'])->name('pub-golf.store');
    Route::post('/pub-golf/joins', [PubGolfCrawlJoinController::class, 'store'])->name('pub-golf.joins.store');
    Route::middleware(EnsurePubGolfCrawlParticipant::class)->group(function () {
        Route::get('/pub-golf/{pubGolfCrawl}', [PubGolfCrawlController::class, 'show'])->name('pub-golf.show');
        Route::post('/pub-golf/{pubGolfCrawl}/drinks', [PubGolfDrinkLogController::class, 'store'])->name('pub-golf.drinks.store');
        Route::post('/pub-golf/{pubGolfCrawl}/custom-drinks', [PubGolfCustomDrinkController::class, 'store'])->name('pub-golf.custom-drinks.store');
        Route::delete('/pub-golf/{pubGolfCrawl}/custom-drinks/{pubGolfCustomDrink}', [PubGolfCustomDrinkController::class, 'destroy'])->name('pub-golf.custom-drinks.destroy');
        Route::post('/pub-golf/{pubGolfCrawl}/drinks/undo', [PubGolfDrinkUndoController::class, 'store'])->name('pub-golf.drinks.undo');
        Route::post('/pub-golf/{pubGolfCrawl}/leave', [PubGolfCrawlLeaveController::class, 'store'])->name('pub-golf.leave.store');
        Route::post('/pub-golf/{pubGolfCrawl}/rejoins', [PubGolfCrawlRejoinController::class, 'store'])->name('pub-golf.rejoins.store');
        Route::get('/pub-golf/{pubGolfCrawl}/chat', [PubGolfChatMessageController::class, 'index'])->name('pub-golf.chat.index');
        Route::post('/pub-golf/{pubGolfCrawl}/chat', [PubGolfChatMessageController::class, 'store'])->name('pub-golf.chat.store');
        Route::post('/pub-golf/{pubGolfCrawl}/chat/read', [PubGolfChatReadController::class, 'store'])->name('pub-golf.chat.read');
        Route::get('/pub-golf/{pubGolfCrawl}/recap', [PubGolfRecapController::class, 'show'])->name('pub-golf.recap.show');
    });
    Route::get('/wickets', [WicketGroupController::class, 'index'])->name('wickets.index');
    Route::get('/wickets/create', [WicketGroupController::class, 'create'])->name('wickets.create');
    Route::post('/wickets', [WicketGroupController::class, 'store'])->name('wickets.store');
    Route::middleware(EnsureWicketGroupMember::class)->group(function () {
        Route::get('/wickets/{wicketGroup}', [WicketGroupController::class, 'show'])->name('wickets.show');
        Route::get('/wickets/{wicketGroup}/live', WicketGroupLiveController::class)->name('wickets.live');
        Route::patch('/wickets/{wicketGroup}', [WicketGroupController::class, 'update'])->name('wickets.update');
        Route::delete('/wickets/{wicketGroup}', [WicketGroupController::class, 'destroy'])->name('wickets.destroy');
        Route::post('/wickets/{wicketGroup}/members', [WicketGroupMemberController::class, 'store'])->name('wickets.members.store');
        Route::patch('/wickets/{wicketGroup}/members/{user}', [WicketGroupMemberController::class, 'update'])
            ->scopeBindings()
            ->name('wickets.members.update');
        Route::delete('/wickets/{wicketGroup}/members/{user}', [WicketGroupMemberController::class, 'destroy'])
            ->scopeBindings()
            ->name('wickets.members.destroy');
        Route::post('/wickets/{wicketGroup}/fines', [WicketFineController::class, 'store'])->name('wickets.fines.store');
        Route::post('/wickets/{wicketGroup}/sips', [WicketSipLogController::class, 'store'])->name('wickets.sips.store');
        Route::post('/wickets/{wicketGroup}/fines/{fine}/completions', [WicketFineCompletionController::class, 'store'])
            ->scopeBindings()
            ->name('wickets.fines.completions.store');
    });
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/geoguessr', [ProfileController::class, 'updateGeoguessr'])->name('profile.geoguessr.update');
    Route::post('/profile/geoguessr/sync', [ProfileController::class, 'syncGeoguessr'])->name('profile.geoguessr.sync');
    Route::get('/profile/geoguessr/challenges', [ProfileController::class, 'geoguessrChallenges'])->name('profile.geoguessr.challenges');
    Route::post('/profile/geoguessr/challenges/share', [ProfileController::class, 'shareGeoguessrChallenge'])->name('profile.geoguessr.challenges.share');
    Route::post('/profile/device-tokens', [DeviceTokenController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('profile.device-tokens.store');
    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    Route::post('/admin/push-notifications', [AdminController::class, 'sendPush'])
        ->middleware('throttle:10,1')
        ->name('admin.push.send');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
