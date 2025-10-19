<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Get all users
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Filter by role
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Get users with squad count
        $users = $query->withCount('squadMembers as squads_count')
                      ->orderBy('created_at', 'desc')
                      ->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Get a single user
     */
    public function show($id)
    {
        $user = User::with(['squadMembers.squad'])
                   ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Create a new user
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => ['required', Rule::in(['admin', 'squad_lead', 'member', 'viewer'])],
            'timezone' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'timezone' => $request->timezone ?? 'America/New_York',
            'is_active' => true,
        ]);

        // Create audit log
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'User created: ' . $user->name,
            'model_type' => 'User',
            'model_id' => $user->id,
            'new_values' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $user
        ], 201);
    }

    /**
     * Update a user
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id)
            ],
            'role' => ['sometimes', 'required', Rule::in(['admin', 'squad_lead', 'member', 'viewer'])],
            'timezone' => 'nullable|string|max:50',
            'password' => 'sometimes|nullable|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $changes = [];
        $oldData = $user->only(['name', 'email', 'role', 'timezone']);

        if ($request->has('name')) {
            $user->name = $request->name;
            $changes['name'] = ['old' => $oldData['name'], 'new' => $request->name];
        }

        if ($request->has('email')) {
            $user->email = $request->email;
            $changes['email'] = ['old' => $oldData['email'], 'new' => $request->email];
        }

        if ($request->has('role')) {
            $user->role = $request->role;
            $changes['role'] = ['old' => $oldData['role'], 'new' => $request->role];
        }

        if ($request->has('timezone')) {
            $user->timezone = $request->timezone;
        }

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
            $changes['password'] = 'changed';
        }

        $user->save();

        // Create audit log
        if (!empty($changes)) {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'User updated: ' . $user->name,
                'model_type' => 'User',
                'model_id' => $user->id,
                'old_values' => $oldData,
                'new_values' => $user->only(['name', 'email', 'role', 'timezone'])
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user
        ]);
    }

    /**
     * Toggle user active status
     */
    public function toggleStatus(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldStatus = $user->is_active;
        $user->is_active = $request->is_active;
        $user->save();

        // Create audit log
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'User ' . ($request->is_active ? 'activated' : 'deactivated') . ': ' . $user->name,
            'model_type' => 'User',
            'model_id' => $user->id,
            'old_values' => ['is_active' => $oldStatus],
            'new_values' => ['is_active' => $request->is_active]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully',
            'data' => $user
        ]);
    }

    /**
     * Delete a user (soft delete)
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete yourself'
            ], 403);
        }

        $userName = $user->name;
        $userId = $user->id;
        $oldValues = $user->only(['name', 'email', 'role']);

        $user->delete();

        // Create audit log
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'User deleted: ' . $userName,
            'model_type' => 'User',
            'model_id' => $userId,
            'old_values' => $oldValues,
            'new_values' => ['deleted' => true]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Get audit logs
     */
    public function auditLogs(Request $request)
    {
        $query = AuditLog::with('user:id,name,email');

        // Limit results
        $limit = $request->get('limit', 50);

        $logs = $query->orderBy('created_at', 'desc')
                     ->limit($limit)
                     ->get()
                     ->map(function($log) {
                         return [
                             'id' => $log->id,
                             'action' => $log->action,
                             'user_name' => $log->user->name ?? 'System',
                             'created_at' => $log->created_at,
                             'model_type' => $log->model_type,
                             'model_id' => $log->model_id,
                         ];
                     });

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }
}
