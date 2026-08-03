<?php

use App\Livewire\Auth\AcceptInvitation;
use App\Livewire\Auth\RequestLoginCode;
use App\Livewire\Auth\VerifyLoginCode;
use App\Livewire\Auth\VerifyTwoFactor;
use App\Livewire\Connections\Manage as ConnectionsManage;
use App\Livewire\Dashboard;
use App\Livewire\Invitations\Manage as InvitationsManage;
use App\Livewire\Modules\Manage as ModulesManage;
use App\Livewire\Profile\Show as ProfileShow;
use App\Livewire\Roles\Manage as RolesManage;
use App\Livewire\UserRoles\Manage as UserRolesManage;
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
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::get('/profile', ProfileShow::class)->name('profile.show');

    Route::get('/invitations', InvitationsManage::class)
        ->middleware('permission:screens.invitations.manage')
        ->name('invitations.index');

    Route::get('/roles', RolesManage::class)
        ->middleware('permission:screens.roles.manage')
        ->name('roles.index');

    Route::get('/user-roles', UserRolesManage::class)
        ->middleware('permission:screens.user-roles.manage')
        ->name('user-roles.index');

    Route::get('/connections', ConnectionsManage::class)
        ->middleware('permission:screens.connections.manage')
        ->name('connections.index');

    Route::get('/modules', ModulesManage::class)
        ->middleware('permission:screens.modules.manage')
        ->name('modules.index');
});
