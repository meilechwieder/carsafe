<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Only a logged-in administrator (admin guard, role "admin") may pass.
 * Logged out: redirect to the admin login. Logged in as anyone else (e.g. dispatcher): 403.
 * Used to lock the maintenance/test routes in routes/tests.php.
 */
class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('admin')->user();

        if (!$user) {
            return redirect()->route('admin.login');
        }

        if (($user->role ?? null) !== 'admin') {
            abort(403);
        }

        return $next($request);
    }
}
