<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of users (Admin only)
     * GET /api/users
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Only admin can view all users
            if (!$user->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only administrators can view user list'
                ], 403);
            }

            $query = User::select(['id', 'name', 'email', 'role', 'phone', 'avatar', 'status', 'created_at']);
            
            // Apply filters
            if ($request->has('role') && $request->role) {
                $query->where('role', $request->role);
            }
            
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }
            
            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }
            
            // Sort
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);
            
            $perPage = $request->get('per_page', 15);
            $users = $query->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total()
                ],
                'message' => 'Users retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving users: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created user (Admin only)
     * POST /api/users
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only administrators can create users'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100',
                'email' => 'required|string|email|max:150|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'required|in:super_admin,organizer,user',
                'phone' => 'nullable|string|max:20',
                'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
                'status' => 'sometimes|in:active,inactive,banned',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userData = $request->only([
                'name', 'email', 'role', 'phone', 'status'
            ]);
            
            $userData['password'] = Hash::make($request->password);
            $userData['status'] = $request->get('status', 'active');

            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                $avatarPath = $request->file('avatar')->store('avatars', 'public');
                $userData['avatar'] = $avatarPath;
            }

            $newUser = User::create($userData);
            
            // Remove password from response
            $newUser->makeHidden(['password']);

            return response()->json([
                'success' => true,
                'data' => $newUser,
                'message' => 'User created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified user (Admin only)
     * GET /api/users/{id}
     */
    public function show($id)
    {
        try {
            $user = Auth::user();
            
            if (!$user->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only administrators can view user details'
                ], 403);
            }

            $targetUser = User::with([
                'events:id,title,status,organizer_id',
                'reviews:id,event_id,rating,status,user_id',
                'transactions:id,user_id,ticket_id,status,amount'
            ])->findOrFail($id);

            $targetUser->makeHidden(['password']);

            return response()->json([
                'success' => true,
                'data' => $targetUser,
                'message' => 'User retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
    }

    /**
     * Update the specified user (Admin only)
     * PUT /api/users/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();
            
            if (!$user->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only administrators can update users'
                ], 403);
            }

            $targetUser = User::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:100',
                'email' => [
                    'sometimes',
                    'required',
                    'string',
                    'email',
                    'max:150',
                    Rule::unique('users')->ignore($targetUser->id)
                ],
                'password' => 'sometimes|nullable|string|min:8|confirmed',
                'role' => 'sometimes|required|in:super_admin,organizer,user',
                'phone' => 'sometimes|nullable|string|max:20',
                'avatar' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
                'status' => 'sometimes|required|in:active,inactive,banned',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userData = $request->only([
                'name', 'email', 'role', 'phone', 'status'
            ]);

            // Handle password update
            if ($request->filled('password')) {
                $userData['password'] = Hash::make($request->password);
            }

            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                // Delete old avatar if exists
                if ($targetUser->avatar) {
                    Storage::disk('public')->delete($targetUser->avatar);
                }
                
                $avatarPath = $request->file('avatar')->store('avatars', 'public');
                $userData['avatar'] = $avatarPath;
            }

            $targetUser->update($userData);
            $targetUser->makeHidden(['password']);

            return response()->json([
                'success' => true,
                'data' => $targetUser,
                'message' => 'User updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified user (Admin only)
     * DELETE /api/users/{id}
     */
    public function destroy($id)
    {
        try {
            $user = Auth::user();
            
            if (!$user->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only administrators can delete users'
                ], 403);
            }

            $targetUser = User::findOrFail($id);

            // Prevent admin from deleting themselves
            if ($targetUser->id === $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete your own account'
                ], 422);
            }

            // Delete avatar if exists
            if ($targetUser->avatar) {
                Storage::disk('public')->delete($targetUser->avatar);
            }

            $targetUser->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change user role (Admin only)
     * PATCH /api/users/{id}/role
     */
    public function changeRole(Request $request, $id)
    {
        try {
            $user = Auth::user();
            
            if (!$user->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only administrators can change user roles'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'role' => 'required|in:super_admin,organizer,user',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $targetUser = User::findOrFail($id);

            // Prevent admin from changing their own role
            if ($targetUser->id === $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot change your own role'
                ], 422);
            }

            $targetUser->update(['role' => $request->role]);
            $targetUser->makeHidden(['password']);

            return response()->json([
                'success' => true,
                'data' => $targetUser,
                'message' => 'User role updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error changing user role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ban user (Admin only)
     * PATCH /api/users/{id}/ban
     */
    public function banUser($id)
    {
        try {
            $user = Auth::user();
            
            if (!$user->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only administrators can ban users'
                ], 403);
            }

            $targetUser = User::findOrFail($id);

            // Prevent admin from banning themselves
            if ($targetUser->id === $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot ban yourself'
                ], 422);
            }

            $targetUser->update(['status' => 'banned']);
            $targetUser->makeHidden(['password']);

            return response()->json([
                'success' => true,
                'data' => $targetUser,
                'message' => 'User banned successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error banning user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unban user (Admin only)
     * PATCH /api/users/{id}/unban
     */
    public function unbanUser($id)
    {
        try {
            $user = Auth::user();
            
            if (!$user->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only administrators can unban users'
                ], 403);
            }

            $targetUser = User::findOrFail($id);
            $targetUser->update(['status' => 'active']);
            $targetUser->makeHidden(['password']);

            return response()->json([
                'success' => true,
                'data' => $targetUser,
                'message' => 'User unbanned successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error unbanning user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user statistics (Admin only)
     * GET /api/users/statistics
     */
    public function statistics()
    {
        try {
            $user = Auth::user();
            
            if (!$user->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only administrators can view user statistics'
                ], 403);
            }

            $stats = [
                'total_users' => User::count(),
                'active_users' => User::where('status', 'active')->count(),
                'inactive_users' => User::where('status', 'inactive')->count(),
                'banned_users' => User::where('status', 'banned')->count(),
                'role_distribution' => [
                    'super_admin' => User::where('role', 'super_admin')->count(),
                    'organizer' => User::where('role', 'organizer')->count(),
                    'user' => User::where('role', 'user')->count(),
                ],
                'recent_registrations' => User::orderBy('created_at', 'desc')
                                              ->limit(5)
                                              ->select(['id', 'name', 'email', 'role', 'created_at'])
                                              ->get(),
                'users_by_month' => User::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
                                       ->whereYear('created_at', date('Y'))
                                       ->groupBy('month')
                                       ->orderBy('month')
                                       ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'User statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current user profile
     * GET /api/profile
     */
    public function profile()
    {
        try {
			/** @var \App\Models\User $user */
            $user = Auth::user();
            $user->makeHidden(['password']);

            return response()->json([
                'success' => true,
                'data' => $user,
                'message' => 'Profile retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update current user profile
     * PUT /api/profile
     */
    public function updateProfile(Request $request)
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:100',
                'email' => [
                    'sometimes',
                    'required',
                    'string',
                    'email',
                    'max:150',
                    Rule::unique('users')->ignore($user->id)
                ],
                'password' => 'sometimes|nullable|string|min:8|confirmed',
                'phone' => 'sometimes|nullable|string|max:20',
                'avatar' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userData = $request->only(['name', 'email', 'phone']);

            // Handle password update
            if ($request->filled('password')) {
                $userData['password'] = Hash::make($request->password);
            }

            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                // Delete old avatar if exists
                if ($user->avatar) {
                    Storage::disk('public')->delete($user->avatar);
                }
                
                $avatarPath = $request->file('avatar')->store('avatars', 'public');
                $userData['avatar'] = $avatarPath;
            }

            $user->update($userData);
            $user->makeHidden(['password']);

            return response()->json([
                'success' => true,
                'data' => $user,
                'message' => 'Profile updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating profile: ' . $e->getMessage()
            ], 500);
        }
    }
}