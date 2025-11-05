<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
	public function register(Request $request)
	{
		$request->validate([
			'name' => 'required|string|max:255',
			'email' => 'required|string|email|max:255|unique:users',
			'password' => 'required|string|min:8|confirmed',
			'phone' => 'nullable|string|max:20',
			'role' => 'nullable|string|in:user,organizer',
		]);

		$user = User::create([
			'name' => $request->name,
			'email' => $request->email,
			'password' => Hash::make($request->password),
			'phone' => $request->phone,
		]);

		$role = $request->role ?? 'user';
		$user->assignRole($role);

		$token = $user->createToken('auth_token')->plainTextToken;

		$user->load(['roles', 'permissions']);

		return response()->json([
			'access_token' => $token,
			'token_type' => 'Bearer',
			'user' => [
				'id' => $user->id,
				'name' => $user->name,
				'email' => $user->email,
				'phone' => $user->phone,
				'roles' => $user->roles->pluck('name'),
				'permissions' => $user->getAllPermissions()->pluck('name'),
				'created_at' => $user->created_at,
				'updated_at' => $user->updated_at,
			],
		], 201);
	}

	public function login(Request $request)
	{
		$request->validate([
			'email' => 'required|email',
			'password' => 'required',
		]);

		$user = User::where('email', $request->email)->first();

		if (!$user || !Hash::check($request->password, $user->password)) {
			throw ValidationException::withMessages([
				'email' => ['The provided credentials are incorrect.'],
			]);
		}

		$token = $user->createToken('auth_token')->plainTextToken;

		$user->load(['roles', 'permissions']);

		return response()->json([
			'access_token' => $token,
			'token_type' => 'Bearer',
			'user' => [
				'id' => $user->id,
				'name' => $user->name,
				'email' => $user->email,
				'phone' => $user->phone,
				'roles' => $user->roles->pluck('name'),
				'permissions' => $user->getAllPermissions()->pluck('name'),
				'created_at' => $user->created_at,
				'updated_at' => $user->updated_at,
			],
		]);
	}

	public function logout(Request $request)
	{
		$request->user()->currentAccessToken()->delete();

		return response()->json([
			'message' => 'Logged out successfully',
		]);
	}

	public function user(Request $request)
	{
		$user = $request->user();
		$user->load(['roles', 'permissions']);

		return response()->json([
			'id' => $user->id,
			'name' => $user->name,
			'email' => $user->email,
			'phone' => $user->phone,
			'roles' => $user->roles->pluck('name'),
			'permissions' => $user->getAllPermissions()->pluck('name'),
			'created_at' => $user->created_at,
			'updated_at' => $user->updated_at,
		]);
	}

	public function changeRole(Request $request)
	{
		$request->validate([
			'user_id' => 'required|exists:users,id',
			'role' => 'required|string|exists:roles,name',
		]);

		if (!$request->user()->hasRole('super_admin')) {
			return response()->json([
				'message' => 'Insufficient permissions to change user roles.'
			], 403);
		}

		$targetUser = User::findOrFail($request->user_id);

		$targetUser->syncRoles([$request->role]);

		$targetUser->load(['roles', 'permissions']);

		return response()->json([
			'message' => 'Role updated successfully',
			'user' => [
				'id' => $targetUser->id,
				'name' => $targetUser->name,
				'email' => $targetUser->email,
				'roles' => $targetUser->roles->pluck('name'),
				'permissions' => $targetUser->getAllPermissions()->pluck('name'),
			],
		]);
	}
}
