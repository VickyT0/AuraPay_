<?php

use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Home
|--------------------------------------------------------------------------
|
| Authenticated users go to the AuraPay dashboard.
| Guests go to the login page.
|
*/
Route::get('/', function () {

    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});


/*
|--------------------------------------------------------------------------
| AuraPay Dashboard
|--------------------------------------------------------------------------
|
| The dashboard is available only to authenticated users.
|
| idle.timeout terminates the authenticated session after
| five minutes of inactivity.
|
*/
Route::middleware([
    'auth',
    'idle.timeout',
])
    ->get(
        '/dashboard',
        function () {

            return view(
                'aurapay.dashboard'
            );
        }
    )
    ->name('dashboard');


/*
|--------------------------------------------------------------------------
| Public Welcome Page
|--------------------------------------------------------------------------
*/
Route::get('/welcome', function () {

    return view(
        'welcome-aurapay'
    );
});