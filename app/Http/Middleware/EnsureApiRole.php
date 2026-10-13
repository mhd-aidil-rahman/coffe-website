<?php

namespace App\Http\Middleware;

use App\Models\StaffMember;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user instanceof StaffMember && ! $user->active) {
            abort(401, 'Akun staff tidak aktif.');
        }

        $role = match (true) {
            $user instanceof User => 'Owner',
            $user instanceof StaffMember => $user->position,
            default => null,
        };

        abort_unless($role !== null && in_array($role, $roles, true), 403, 'Anda tidak memiliki akses untuk fitur ini.');

        return $next($request);
    }
}
