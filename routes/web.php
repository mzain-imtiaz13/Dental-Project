<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\UserController;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Redirect root to login page
Route::get('/', function () {
    return redirect()->route('login');
});

// Auth routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Protected routes (only for logged-in users)
Route::middleware('auth')->group(function () {

    Route::get('/dashboard', function () {
        return view('dashboard'); 
    })->name('dashboard');

    Route::get('/orders', function () {
        return view('orders');
    })->name('orders');

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

    Route::get('/add-users', function () {
        return view('addUsersForm');
    })->name('add-users');

    // 🚀 Store new user
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
});
