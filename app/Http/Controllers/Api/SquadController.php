<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Squad;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SquadController extends Controller
{
    /**
     * Display a listing of squads.
     */
    public function index(Request $request)
    {
        $query = Squad::with(['members', 'sprints' => function ($q) {
            $q->where('status', 'active');
        }]);

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $squads = $query->orderBy('name')->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $squads->items(),
            'meta' => [
                'current_page' => $squads->currentPage(),
                'last_page' => $squads->lastPage(),
                'per_page' => $squads->perPage(),
                'total' => $squads->total(),
            ]
        ]);
    }

    /**
     * Store a newly created squad.
     */
    public function store(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'timezone' => 'required|string|max:50',
            'workdays' => 'nullable|array',
            'workdays.*' => 'integer|between:1,7',
            'sprint_duration_days' => 'required|integer|min:1|max:30',
            'jira_board_id' => 'nullable|string|max:100',
            'project_key' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $squad = Squad::create([
            'name' => $request->name,
            'description' => $request->description,
            'timezone' => $request->timezone,
            'workdays' => $request->workdays ?? [1, 2, 3, 4, 5], // Default Mon-Fri
            'sprint_duration_days' => $request->sprint_duration_days,
            'jira_board_id' => $request->jira_board_id,
            'project_key' => $request->project_key,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Squad created successfully',
            'data' => $squad
        ], 201);
    }

    /**
     * Display the specified squad.
     */
    public function show(string $id)
    {
        $squad = Squad::with(['members', 'sprints' => function ($query) {
            $query->orderBy('start_date', 'desc')->limit(5);
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $squad
        ]);
    }

    /**
     * Update the specified squad.
     */
    public function update(Request $request, string $id)
    {
        $squad = Squad::findOrFail($id);

        // Only admin or squad lead can update
        if (!$request->user()->isAdmin() && !$squad->leads->contains($request->user()->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'timezone' => 'sometimes|required|string|max:50',
            'workdays' => 'nullable|array',
            'workdays.*' => 'integer|between:1,7',
            'sprint_duration_days' => 'sometimes|required|integer|min:1|max:30',
            'jira_board_id' => 'nullable|string|max:100',
            'project_key' => 'nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $squad->update($request->only([
            'name',
            'description',
            'timezone',
            'workdays',
            'sprint_duration_days',
            'jira_board_id',
            'project_key',
            'is_active',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Squad updated successfully',
            'data' => $squad->fresh()
        ]);
    }

    /**
     * Remove the specified squad.
     */
    public function destroy(Request $request, string $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $squad = Squad::findOrFail($id);
        $squad->delete();

        return response()->json([
            'success' => true,
            'message' => 'Squad deleted successfully'
        ]);
    }

    /**
     * Get squad members
     */
    public function members(string $id)
    {
        $squad = Squad::findOrFail($id);
        $members = $squad->members()->withPivot('role', 'joined_at', 'left_at', 'is_active')->get();

        return response()->json([
            'success' => true,
            'data' => $members
        ]);
    }

    /**
     * Add member to squad
     */
    public function addMember(Request $request, string $id)
    {
        $squad = Squad::findOrFail($id);

        if (!$request->user()->isAdmin() && !$squad->leads->contains($request->user()->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:lead,member',
            'joined_at' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if already a member
        if ($squad->members()->where('user_id', $request->user_id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'User is already a member of this squad'
            ], 422);
        }

        $squad->members()->attach($request->user_id, [
            'role' => $request->role,
            'joined_at' => $request->joined_at,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Member added successfully',
            'data' => $squad->fresh()->load('members')
        ]);
    }

    /**
     * Remove member from squad
     */
    public function removeMember(Request $request, string $id, string $userId)
    {
        $squad = Squad::findOrFail($id);

        if (!$request->user()->isAdmin() && !$squad->leads->contains($request->user()->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $squad->members()->updateExistingPivot($userId, [
            'is_active' => false,
            'left_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Member removed from squad successfully'
        ]);
    }

    /**
     * Get squad sprints
     */
    public function sprints(string $id)
    {
        $squad = Squad::findOrFail($id);
        $sprints = $squad->sprints()->orderBy('start_date', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $sprints
        ]);
    }

    /**
     * Get active sprint for squad
     */
    public function activeSprint(string $id)
    {
        $squad = Squad::findOrFail($id);
        $activeSprint = $squad->activeSprint();

        if (!$activeSprint) {
            return response()->json([
                'success' => true,
                'message' => 'No active sprint found',
                'data' => null
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $activeSprint->load('attendanceRecords')
        ]);
    }
}
