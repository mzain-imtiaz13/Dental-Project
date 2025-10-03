<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ApiCredentialController;
use App\Http\Controllers\OAuthController;
use App\Models\User;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\GroupController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('login');
});

// Auth
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {

    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');

    // Orders & Cases (DB-backed JSON)
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/cases',  [CaseController::class,  'index'])->name('cases.index');

    // Profiles & Groups views (DB-backed)
    Route::get('/profiles', [ProfileController::class, 'index'])->name('profiles.index');
    Route::get('/groups',   [GroupController::class,  'index'])->name('groups.index');

    // Optional: per-credential remote pulls (kept)
    Route::get('api-credentials/{apiCredential}/orders', [OrderController::class, 'byCredential'])->name('api-credentials.orders');
    Route::get('api-credentials/{apiCredential}/cases',  [CaseController::class,  'byCredential'])->name('api-credentials.cases');

    // Users (unchanged)
    Route::get('/users', function () {
        $users = User::select('name', 'email', 'role', 'created_at')->get()->map(function ($u) {
            return [
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role ?? 'user',
                'created' => optional($u->created_at)->toDateString(),
            ];
        });
        return view('users', ['users' => $users]);
    })->name('users');
    Route::get('/add-users', fn () => view('addUsersForm'))->name('add-users');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');

    // API Credentials
    Route::resource('api-credentials', ApiCredentialController::class);
    Route::patch('api-credentials/{apiCredential}/toggle', [ApiCredentialController::class, 'toggle'])->name('api-credentials.toggle');
    Route::post('api-credentials/{apiCredential}/test', [ApiCredentialController::class, 'test'])->name('api-credentials.test');

    // OAuth
    Route::get('/oauth/authorize', [OAuthController::class, 'authorize'])->name('oauth.authorize');
    Route::get('/oauth/callback', [OAuthController::class, 'callback'])->name('oauth.callback');
    Route::post('/oauth/{apiCredential}/refresh', [OAuthController::class, 'refresh'])->name('oauth.refresh');
});
