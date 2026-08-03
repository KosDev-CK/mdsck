<?php

use App\Livewire\Auth\AcceptInvitation;
use App\Livewire\Auth\RequestLoginCode;
use App\Livewire\Auth\VerifyLoginCode;
use App\Livewire\Auth\VerifyTwoFactor;
use App\Models\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', RequestLoginCode::class)->name('login');
    Route::get('/login/verify', VerifyLoginCode::class)->name('login.verify');
    Route::get('/login/two-factor', VerifyTwoFactor::class)->name('login.two-factor');
    Route::get('/invitations/{token}', AcceptInvitation::class)->name('invitations.accept');
});

Route::post('/logout', function (Request $request) {
    $user = Auth::user();

    if ($user) {
        $user->forceFill(['current_session_id' => null])->save();
        SecurityEvent::log(SecurityEvent::LOGOUT, $request, $user);
    }

    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});
