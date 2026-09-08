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
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WicketFineCompletionController;
use App\Http\Controllers\WicketFineController;
use App\Http\Controllers\WicketGroupController;
use App\Http\Controllers\WicketGroupMemberController;
use App\Http\Controllers\WicketSipLogController;
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
    Route::get('/wickets', [WicketGroupController::class, 'index'])->name('wickets.index');
    Route::get('/wickets/create', [WicketGroupController::class, 'create'])->name('wickets.create');
    Route::post('/wickets', [WicketGroupController::class, 'store'])->name('wickets.store');
    Route::get('/wickets/{wicketGroup}', [WicketGroupController::class, 'show'])->name('wickets.show');
    Route::patch('/wickets/{wicketGroup}', [WicketGroupController::class, 'update'])->name('wickets.update');
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
