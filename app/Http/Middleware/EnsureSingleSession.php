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

            $mismatchedSession = $user->current_session_id && $user->current_session_id !== $request->session()->getId();
            $deactivated = ! $user->is_active;

            if ($mismatchedSession || $deactivated) {
                SecurityEvent::log(SecurityEvent::SESSION_REVOKED, $request, $user, meta: ['reason' => $deactivated ? 'deactivated' : 'session_mismatch']);

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('status', $deactivated
                        ? 'Tu cuenta fue desactivada.'
                        : 'Tu sesión se cerró porque iniciaste sesión desde otro dispositivo.');
            }
        }

        return $next($request);
    }
}
