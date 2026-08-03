<?php

namespace App\Http\Middleware;

use App\Models\SecurityEvent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSingleSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user->current_session_id && $user->current_session_id !== $request->session()->getId()) {
                SecurityEvent::log(SecurityEvent::SESSION_REVOKED, $request, $user);

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('status', 'Tu sesión se cerró porque iniciaste sesión desde otro dispositivo.');
            }
        }

        return $next($request);
    }
}
