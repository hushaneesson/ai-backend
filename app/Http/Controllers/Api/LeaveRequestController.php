<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\Squad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeaveRequestController extends Controller
{
    /**
     * Display a listing of leave requests.
     */
    public function index(Request $request)
    {
        $query = LeaveRequest::with(['user', 'squad', 'approver']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by squad
        if ($request->has('squad_id')) {
            $query->where('squad_id', $request->squad_id);
        }

        // Filter by user (for personal leave requests)
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // If not admin, only show own requests or requests for managed squads
        if (!$request->user()->isAdmin()) {
            $query->where(function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)
                  ->orWhereHas('squad.leads', function ($sq) use ($request) {
                      $sq->where('user_id', $request->user()->id);
                  });
            });
        }

        $leaveRequests = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $leaveRequests->items(),
            'meta' => [
                'current_page' => $leaveRequests->currentPage(),
                'last_page' => $leaveRequests->lastPage(),
                'per_page' => $leaveRequests->perPage(),
                'total' => $leaveRequests->total(),
            ]
        ]);
    }

    /**
     * Store a newly created leave request.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'squad_id' => 'nullable|exists:squads,id',
            'leave_type' => 'required|in:vacation,sick,public_holiday,training,other',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:1000',
            'attachments' => 'nullable|array',
            'attachments.*' => 'string|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Calculate total days
        $startDate = new \DateTime($request->start_date);
        $endDate = new \DateTime($request->end_date);
        $totalDays = $startDate->diff($endDate)->days + 1;

        $leaveRequest = LeaveRequest::create([
            'user_id' => $request->user()->id,
            'squad_id' => $request->squad_id,
            'leave_type' => $request->leave_type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'total_days' => $totalDays,
            'reason' => $request->reason,
            'attachments' => $request->attachments,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave request submitted successfully',
            'data' => $leaveRequest->load(['user', 'squad'])
        ], 201);
    }

    /**
     * Display the specified leave request.
     */
    public function show(Request $request, string $id)
    {
        $leaveRequest = LeaveRequest::with(['user', 'squad', 'approver', 'approvals'])
            ->findOrFail($id);

        // Check authorization
        if (!$request->user()->isAdmin()
            && $leaveRequest->user_id !== $request->user()->id
            && !$leaveRequest->squad->leads->contains($request->user()->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $leaveRequest
        ]);
    }

    /**
     * Update the specified leave request.
     */
    public function update(Request $request, string $id)
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        // Only owner can update and only if still pending
        if ($leaveRequest->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($leaveRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update non-pending leave request'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'leave_type' => 'sometimes|in:vacation,sick,public_holiday,training,other',
            'start_date' => 'sometimes|date|after_or_equal:today',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'reason' => 'sometimes|string|max:1000',
            'attachments' => 'nullable|array',
            'attachments.*' => 'string|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Recalculate total days if dates changed
        if ($request->has('start_date') || $request->has('end_date')) {
            $startDate = new \DateTime($request->start_date ?? $leaveRequest->start_date);
            $endDate = new \DateTime($request->end_date ?? $leaveRequest->end_date);
            $leaveRequest->total_days = $startDate->diff($endDate)->days + 1;
        }

        $leaveRequest->update($request->only([
            'leave_type',
            'start_date',
            'end_date',
            'reason',
            'attachments'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Leave request updated successfully',
            'data' => $leaveRequest->fresh()->load(['user', 'squad'])
        ]);
    }

    /**
     * Cancel/delete the specified leave request.
     */
    public function destroy(Request $request, string $id)
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        // Only owner can cancel
        if ($leaveRequest->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $leaveRequest->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Leave request cancelled successfully'
        ]);
    }

    /**
     * Approve a leave request
     */
    public function approve(Request $request, string $id)
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        // Check authorization - must be squad lead or admin
        if (!$request->user()->isAdmin() && !$leaveRequest->squad->leads->contains($request->user()->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($leaveRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Leave request is not pending'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'comments' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $leaveRequest->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        // Create approval record
        $leaveRequest->approvals()->create([
            'approver_id' => $request->user()->id,
            'level' => $request->user()->isAdmin() ? 'admin' : 'squad_lead',
            'status' => 'approved',
            'comments' => $request->comments,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave request approved successfully',
            'data' => $leaveRequest->fresh()->load(['user', 'squad', 'approver'])
        ]);
    }

    /**
     * Reject a leave request
     */
    public function reject(Request $request, string $id)
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        // Check authorization
        if (!$request->user()->isAdmin() && !$leaveRequest->squad->leads->contains($request->user()->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($leaveRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Leave request is not pending'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $leaveRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        // Create approval record
        $leaveRequest->approvals()->create([
            'approver_id' => $request->user()->id,
            'level' => $request->user()->isAdmin() ? 'admin' : 'squad_lead',
            'status' => 'rejected',
            'comments' => $request->rejection_reason,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave request rejected',
            'data' => $leaveRequest->fresh()->load(['user', 'squad', 'approver'])
        ]);
    }

    /**
     * Get pending approvals for current user
     */
    public function pendingApprovals(Request $request)
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isSquadLead()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $query = LeaveRequest::with(['user', 'squad'])
            ->where('status', 'pending');

        // If squad lead, only show their squads
        if (!$user->isAdmin()) {
            $query->whereHas('squad.leads', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        $pendingRequests = $query->orderBy('created_at', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $pendingRequests
        ]);
    }

    /**
     * Get leave calendar view
     */
    public function calendar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'squad_id' => 'required|exists:squads,id',
            'month' => 'required|date_format:Y-m',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $startDate = $request->month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        $leaveRequests = LeaveRequest::with(['user'])
            ->where('squad_id', $request->squad_id)
            ->where('status', 'approved')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      ->orWhere(function ($q) use ($startDate, $endDate) {
                          $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                      });
            })
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'month' => $request->month,
                'leave_requests' => $leaveRequests,
            ]
        ]);
    }
}
