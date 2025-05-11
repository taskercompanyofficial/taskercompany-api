<?php

namespace App\Http\Controllers\Crm\Authenticated;

use App\Events\NewNotification;
use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StaffAttendence;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class CrmAttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 50);
        $page = $request->input('page', 1);
        $from = $request->input('from') ?? now()->startOfDay()->toDateTimeString();
        $to = $request->input('to') ?? now()->endOfDay()->toDateTimeString();

        // Get current user and role
        $user = $request->user();
        $userRole = $user->role;
        $userBranchId = $user->branch_id;

        // Build attendance query with filters
        $attendanceQuery = StaffAttendence::query()
            ->with('staff')
            ->orderByDesc('created_at');

        // Filter by date range
        if ($from && $to) {
            $attendanceQuery->whereBetween('created_at', [$from, $to]);
        }

        // Apply branch filter based on user role
        if (!in_array($userRole, ['general-manager', 'administrator'])) {
            // For branch manager and accountant, only show staff from their branch
            $attendanceQuery->whereHas('staff', function ($query) use ($userBranchId) {
                $query->where('branch_id', $userBranchId);
            });
        }

        // Paginate attendance records
        $attendance = $attendanceQuery->paginate($perPage, ['*'], 'page', $page);

        // Transform attendance data
        $attendanceData = $attendance->map(function ($record) {
            return [
                'id' => $record->id,
                'employee_name' => $record->staff?->full_name ?? 'Unknown',
                'check_in' => $record->check_in ?? 'not checked in',
                'check_in_location' => $record->check_in_location ?? 'not checked in',
                'check_out' => $record->check_out ?? 'not checked out',
                'check_out_location' => $record->check_out_location ?? 'not checked out',
                'total_hours' => $record->check_in && $record->check_out ?
                    round((strtotime($record->check_out) - strtotime($record->check_in)) / 3600, 2) :
                    0,
                'status' => $record->check_out ? 'checked_out' : ($record->check_in ? 'checked_in' : 'absent')
            ];
        });

        // Prepare custom pagination data
        $paginationData = [
            'current_page' => $attendance->currentPage(),
            'last_page' => $attendance->lastPage(),
            'first_page' => 1,
            'per_page' => $attendance->perPage(),
            'total' => $attendance->total(),
            'next_page' => ($attendance->currentPage() < $attendance->lastPage()) ? $attendance->currentPage() + 1 : null,
            'prev_page' => ($attendance->currentPage() > 1) ? $attendance->currentPage() - 1 : null,
        ];

        // Return JSON response
        return response()->json([
            'data' => $attendanceData,
            'pagination' => $paginationData,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
    public function markPresent(Request $request, $id)
    {
        $validator = $request->validate([
            'location' => 'required|string',
            'longitude' => 'required|numeric', 
            'latitude' => 'required|numeric',
            'date' => 'required|date'
        ]);

        $user = Staff::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        // Parse the exact date time from request
        $date = Carbon::parse($request->date);

        // Check if attendance record exists for this date
        $attendance = StaffAttendence::where('staff_id', $id)
            ->whereDate('created_at', $date->format('Y-m-d'))
            ->first();

        if ($attendance && $attendance->check_in !== null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Already checked in for ' . $date->format('Y-m-d')
            ], 422);
        }

        // Create new attendance record with exact timestamp
        $attendance = new StaffAttendence();
        $attendance->staff_id = $id;
        $attendance->check_in = $date;
        $attendance->check_in_location = $request->location;
        $attendance->check_in_longitude = $request->longitude;
        $attendance->check_in_latitude = $request->latitude;
        $attendance->created_at = $date;
        $attendance->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Check in successful for ' . $date->format('Y-m-d'),
            'data' => $attendance
        ]);
    }

    public function markAbsent(Request $request, $id)
    {
        $validator = $request->validate([
            'date' => 'required|date'
        ]);

        $user = Staff::find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        // Parse exact date from request
        $date = Carbon::parse($request->date);

        // Find or create attendance record for this date
        $attendance = StaffAttendence::firstOrNew([
            'staff_id' => $id,
            'created_at' => $date
        ]);

        // Mark all fields as null to indicate absence
        $attendance->check_in = null;
        $attendance->check_in_location = null;
        $attendance->check_in_longitude = null;
        $attendance->check_in_latitude = null;
        $attendance->check_out = null;
        $attendance->check_out_location = null;
        $attendance->check_out_longitude = null;
        $attendance->check_out_latitude = null;
        $attendance->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Attendance marked as absent for ' . $date->format('Y-m-d'),
            'data' => $attendance
        ]);
    }

    public function getDailyStats(Request $request)
    {
        $user = $request->user();
        $userRole = $user->role;
        $userBranchId = $user->branch_id;

        // First get all active staff
        $staffQuery = Staff::query()
            ->where('status', 'active')
            ->with(['jobs' => function ($query) {
                $query->whereIn('status', ['pending', 'open', 'closed'])
                    ->where('status', '!=', 'cancelled');
            }]);

        if ($userRole !== 'administrator') {
            $staffQuery->where('branch_id', $userBranchId);
        }

        $allStaff = $staffQuery->get();

        // Get today's attendance records
        $attendanceQuery = StaffAttendence::query()
            ->whereDate('created_at', Carbon::today());

        if ($userRole !== 'administrator') {
            $attendanceQuery->whereHas('staff', function($query) use ($userBranchId) {
                $query->where('branch_id', $userBranchId);
            });
        }

        $todayAttendance = $attendanceQuery->get()
            ->keyBy('staff_id');

        $present = collect();
        $absent = collect();

        // Process each staff member
        foreach ($allStaff as $staff) {
            $attendance = $todayAttendance->get($staff->id);

            // Calculate job counts
            $jobs = $staff->jobs;
            $assignedCount = $jobs->count();
            $acceptedCount = $jobs->where('status', 'open')->count();
            $closedCount = $jobs->where('status', 'closed')
                ->whereBetween('updated_at', [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()])
                ->count();

            $staffData = [
                'id' => $staff->id,
                'full_name' => $staff->full_name,
                'profile_image' => $staff->profile_image,
                'assigned_jobs_count' => $assignedCount,
                'accepted_jobs_count' => $acceptedCount,
                'closed_jobs_count' => $closedCount
            ];

            if ($attendance && $attendance->check_in) {
                // Staff is present
                $staffData['status'] = $attendance->check_out ? 'Checked Out' : 'Present';
                $staffData['check_in'] = $attendance->check_in;
                $staffData['check_out'] = $attendance->check_out;
                $present->push($staffData);
            } else {
                // Staff is absent
                $staffData['status'] = 'Absent';
                $staffData['message'] = 'No attendance record found for today';
                $absent->push($staffData);
            }
        }

        return response()->json([
            'present' => $present->values(),
            'absent' => $absent->values()
        ]);
    }
}
