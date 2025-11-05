<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class RoleMiddleware
{
	/**
	 * Handle an incoming request.
	 *
	 * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
	 */
	public function handle(Request $request, Closure $next, ...$roles): Response
	{
		if (!auth()->check()) {
			return response()->json(['message' => 'Unauthorized'], 401);
		}

		/** @var User $user */
		$user = auth()->user();

		foreach ($roles as $role) {
			if ($user->hasRole($role)) {
				return $next($request);
			}
		}

		return response()->json(['message' => 'Insufficient permissions'], 403);
	}
}
