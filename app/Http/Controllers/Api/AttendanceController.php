<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Squad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{
    /**
     * Display a listing of attendance records.
     */
    public function index(Request $request)
    {
        $query = AttendanceRecord::with(['user', 'squad', 'sprint']);

        // Filter by date
        if ($request->has('date')) {
            $query->whereDate('date', $request->date);
        }

        // Filter by squad
        if ($request->has('squad_id')) {
            $query->where('squad_id', $request->squad_id);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        $attendance = $query->orderBy('date', 'desc')
            ->orderBy('check_in_time', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $attendance->items(),
            'meta' => [
                'current_page' => $attendance->currentPage(),
                'last_page' => $attendance->lastPage(),
                'per_page' => $attendance->perPage(),
                'total' => $attendance->total(),
            ]
        ]);
    }

    /**
     * Check in for the day
     */
    public function checkIn(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'squad_id' => 'required|exists:squads,id',
            'work_mode' => 'required|in:remote,office,client_site,ooo',
            'event_tag' => 'nullable|in:standup,retro,planning,demo,regular',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $today = now()->toDateString();

        // Check if already checked in today
        $existingRecord = AttendanceRecord::where('user_id', $user->id)
            ->where('squad_id', $request->squad_id)
            ->whereDate('date', $today)
            ->where('check_out_time', null)
            ->first();

        if ($existingRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Already checked in today for this squad'
            ], 400);
        }

        // Get active sprint for the squad
        $squad = Squad::find($request->squad_id);
        $activeSprint = $squad->activeSprint();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'squad_id' => $request->squad_id,
            'sprint_id' => $activeSprint?->id,
            'date' => $today,
            'check_in_time' => now(),
            'work_mode' => $request->work_mode,
            'event_tag' => $request->event_tag ?? 'regular',
            'status' => 'full_day',
            'check_in_ip' => $request->ip(),
            'check_in_latitude' => $request->latitude,
            'check_in_longitude' => $request->longitude,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Checked in successfully',
            'data' => $attendance->load(['user', 'squad', 'sprint'])
        ], 201);
    }

    /**
     * Check out
     */
    public function checkOut(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'attendance_id' => 'required|exists:attendance_records,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $attendance = AttendanceRecord::findOrFail($request->attendance_id);

        // Verify ownership
        if ($attendance->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($attendance->check_out_time) {
            return response()->json([
                'success' => false,
                'message' => 'Already checked out'
            ], 422);
        }

        $attendance->update([
            'check_out_time' => now(),
            'check_out_ip' => $request->ip(),
            'check_out_latitude' => $request->latitude,
            'check_out_longitude' => $request->longitude,
            'notes' => $request->notes ? $attendance->notes . "\n" . $request->notes : $attendance->notes,
            'total_hours' => $attendance->calculateTotalHours(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Checked out successfully',
            'data' => $attendance->fresh()->load(['user', 'squad', 'sprint'])
        ]);
    }

    /**
     * Display the specified attendance record.
     */
    public function show(string $id)
    {
        $attendance = AttendanceRecord::with(['user', 'squad', 'sprint'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $attendance
        ]);
    }

    /**
     * Update the specified attendance record.
     */
    public function update(Request $request, string $id)
    {
        $attendance = AttendanceRecord::findOrFail($id);

        // Only admin or squad lead can edit
        if (!$request->user()->isAdmin() && !$request->user()->isSquadLead()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'work_mode' => 'nullable|in:remote,office,client_site,ooo',
            'event_tag' => 'nullable|in:standup,retro,planning,demo,regular',
            'status' => 'nullable|in:full_day,partial_day,leave',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $attendance->update($request->only([
            'work_mode',
            'event_tag',
            'status',
            'notes'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Attendance record updated successfully',
            'data' => $attendance->fresh()->load(['user', 'squad', 'sprint'])
        ]);
    }

    /**
     * Remove the specified attendance record.
     */
    public function destroy(Request $request, string $id)
    {
        // Only admin can delete
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $attendance = AttendanceRecord::findOrFail($id);
        $attendance->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attendance record deleted successfully'
        ]);
    }

    /**
     * Get today's attendance for current user
     */
    public function today(Request $request)
    {
        $attendance = AttendanceRecord::where('user_id', $request->user()->id)
            ->whereDate('date', now()->toDateString())
            ->with(['squad', 'sprint'])
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data' => $attendance
        ]);
    }

    /**
     * Get today's attendance for entire squad (presence board)
     */
    public function squadToday(Request $request, string $squadId)
    {
        $squad = Squad::findOrFail($squadId);

        $attendance = AttendanceRecord::where('squad_id', $squadId)
            ->whereDate('date', now()->toDateString())
            ->with(['user'])
            ->get();

        // Get all squad members
        $members = $squad->members()->wherePivot('is_active', true)->get();

        // Combine with attendance data
        $presenceBoard = $members->map(function ($member) use ($attendance) {
            $record = $attendance->firstWhere('user_id', $member->id);
            return [
                'user' => $member,
                'attendance' => $record,
                'is_present' => $record !== null,
                'work_mode' => $record?->work_mode,
                'check_in_time' => $record?->check_in_time,
                'check_out_time' => $record?->check_out_time,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'squad' => $squad,
                'date' => now()->toDateString(),
                'presence_board' => $presenceBoard,
                'summary' => [
                    'total_members' => $members->count(),
                    'present' => $attendance->count(),
                    'absent' => $members->count() - $attendance->count(),
                ]
            ]
        ]);
    }

    /**
     * Get attendance statistics
     */
    public function stats(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'squad_id' => 'required|exists:squads,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $attendance = AttendanceRecord::where('squad_id', $request->squad_id)
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->get();

        $stats = [
            'total_records' => $attendance->count(),
            'total_hours' => $attendance->sum('total_hours'),
            'average_hours_per_day' => $attendance->avg('total_hours'),
            'work_mode_breakdown' => $attendance->groupBy('work_mode')->map->count(),
            'event_breakdown' => $attendance->groupBy('event_tag')->map->count(),
            'status_breakdown' => $attendance->groupBy('status')->map->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
