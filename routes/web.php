<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ApiCredentialController;
use App\Http\Controllers\OAuthController;            // shared Medit + 3Shape callback
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ThreeShapeOAuthController;
use App\Http\Controllers\ThreeShapeCaseController;

/*
|--------------------------------------------------------------------------
| Public / Auth Routes
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => redirect()->route('login'));

Route::get('/login',  [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Protected App Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');

    // Orders screen
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');

    // Unified Cases screen (Medit + 3Shape merged list)
    Route::get('/cases', [CaseController::class, 'index'])->name('cases.index');

    // Profiles and Groups
    Route::get('/profiles', [ProfileController::class, 'index'])->name('profiles.index');
    Route::get('/groups',   [GroupController::class,  'index'])->name('groups.index');

    // Per-credential views
    Route::get('api-credentials/{apiCredential}/orders', [OrderController::class, 'byCredential'])
        ->name('api-credentials.orders');

    Route::get('api-credentials/{apiCredential}/cases', [CaseController::class, 'byCredential'])
        ->name('api-credentials.cases');

    // Users (list + add + create)
    Route::get('/users', function () {
        $users = \App\Models\User::select('name','email','role','created_at')
            ->get()
            ->map(fn($u) => [
                'name'    => $u->name,
                'email'   => $u->email,
                'role'    => $u->role ?? 'user',
                'created' => optional($u->created_at)->toDateString(),
            ]);

        return view('users', ['users' => $users]);
    })->name('users');

    Route::get('/add-users', fn () => view('addUsersForm'))->name('add-users');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');

    // API Credentials CRUD / admin actions
    Route::resource('api-credentials', ApiCredentialController::class);

    Route::patch(
        'api-credentials/{apiCredential}/toggle',
        [ApiCredentialController::class, 'toggle']
    )->name('api-credentials.toggle');

    Route::post(
        'api-credentials/{apiCredential}/test',
        [ApiCredentialController::class, 'test']
    )->name('api-credentials.test');

    Route::post(
        'api-credentials/{apiCredential}/fetch-dscore-orders',
        [ApiCredentialController::class, 'fetchDScoreOrders']
    )->name('api-credentials.fetch-dscore-orders');

    /*
    |--------------------------------------------------------------------------
    | OAuth Flows
    |--------------------------------------------------------------------------
    |
    | - Medit uses /oauth/authorize to start
    | - 3Shape uses /oauth/3shape/start (PKCE)
    | - BOTH return to the same shared /oauth/callback,
    |   which decides whether it's Medit or 3Shape based
    |   on query params + session.
    |
    */

    // Medit start
    Route::get('/oauth/authorize', [OAuthController::class, 'authorize'])
        ->name('oauth.authorize');

    // Shared callback (Medit + 3Shape handled internally)
    Route::get('/oauth/callback', [OAuthController::class, 'sharedCallback'])
        ->name('oauth.callback');

    // 3Shape start (PKCE)
    Route::match(['GET','POST'], '/oauth/3shape/start', [ThreeShapeOAuthController::class, 'start'])
        ->name('oauth.3shape.start');

    // 3Shape manual refresh using stored refresh_token
    Route::post('/oauth/3shape/{credential}/refresh', [ThreeShapeOAuthController::class, 'refresh'])
        ->name('oauth.3shape.refresh');

    /*
    |--------------------------------------------------------------------------
    | 3Shape Case Sync / Debug Views
    |--------------------------------------------------------------------------
    |
    | These are the "raw" 3Shape views:
    | - /threeshape/cases: table directly from three_shape_cases table
    | - /threeshape/cases/sync: pulls from 3Shape API and upserts
    | - /threeshape/cases/list: JSON list for that page
    |
    | We keep these because they’re useful for debugging 3Shape specifically.
    | The main "Cases" page ( /cases ) already shows merged data.
    |
    */

    Route::get('/threeshape/cases', [ThreeShapeCaseController::class, 'index'])
        ->name('threeshape.cases');

    Route::get('/threeshape/cases/list', [ThreeShapeCaseController::class, 'list'])
        ->name('threeshape.cases.list');

    Route::get('/threeshape/cases/{uuid}/detail', [ThreeShapeCaseController::class, 'detail'])
        ->name('threeshape.cases.detail');

    Route::get('/threeshape/cases/sync', [ThreeShapeCaseController::class, 'sync'])
        ->name('threeshape.cases.sync');

        // 3Shape file proxy (attachments / scans download)
    Route::get('/threeshape/file', [ThreeShapeCaseController::class, 'proxyFile'])
        ->name('threeshape.file');
});
