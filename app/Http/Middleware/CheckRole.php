<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless(
            $user !== null
                && $user->isActive()
                && in_array($user->role?->name, Role::NAMES, true)
                && in_array($user->role->name, $roles, true),
            403,
        );

        return $next($request);
    }
}
