<?php

namespace App\Http\Controllers\Authenticated\Staff;

use App\Http\Controllers\Controller;
use App\Models\StaffAttendence;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use App\Events\NewNotification;
use App\Models\Staff;

class AttendenceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Set default dates if not provided in request
        $startDate = $request->has('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $endDate = $request->has('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : now()->endOfDay();

        $filter = $request->input('filter', 'all');

        // Get existing attendance records first
        $query = StaffAttendence::where('staff_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc');

        if ($filter !== 'all') {
            if ($filter === 'present') {
                $query->whereNotNull('check_in');
            } elseif ($filter === 'late') {
                $query->whereNotNull('check_in')
                    ->whereRaw("TIME(check_in) > '10:00:00'");
            } elseif ($filter === 'absent') {
                $query->whereNull('check_in');
            }
        }

        $existingAttendances = $query->get();

        // Generate array of all dates in range
        $period = Carbon::parse($startDate)->daysUntil($endDate);
        $allDates = [];
        foreach ($period as $date) {
            $allDates[] = $date->format('Y-m-d');
        }

        // Map attendance records for all dates
        $attendances = collect($allDates)->map(function ($date) use ($existingAttendances, $user) {
            // Find existing attendance for this date
            $existing = $existingAttendances->first(function ($attendance) use ($date) {
                return Carbon::parse($attendance->created_at)->format('Y-m-d') === $date;
            });

            if ($existing) {
                return [
                    'id' => $existing->id,
                    'staff_id' => $existing->staff_id,
                    'check_in' => $existing->check_in,
                    'check_in_location' => $existing->check_in_location,
                    'check_in_longitude' => $existing->check_in_longitude,
                    'check_in_latitude' => $existing->check_in_latitude,
                    'check_out' => $existing->check_out,
                    'check_out_location' => $existing->check_out_location,
                    'check_out_longitude' => $existing->check_out_longitude,
                    'check_out_latitude' => $existing->check_out_latitude,
                    'created_at' => Carbon::parse($date)->format('Y-m-d\TH:i:s.u\Z'),
                    'updated_at' => $existing->updated_at
                ];
            }

            // Create virtual attendance record for missing date
            return [
                'id' => null,
                'staff_id' => $user->id,
                'check_in' => null,
                'check_in_location' => null,
                'check_in_longitude' => null,
                'check_in_latitude' => null,
                'check_out' => null,
                'check_out_location' => null,
                'check_out_longitude' => null,
                'check_out_latitude' => null,
                'created_at' => Carbon::parse($date)->format('Y-m-d\TH:i:s.u\Z'),
                'updated_at' => null
            ];
        });

        // Calculate monthly stats
        $monthlyStats = [
            'total_days' => count($allDates),
            'present' => $attendances->filter(function ($attendance) {
                return $attendance['check_in'] !== null;
            })->count(),
            'late' => $attendances->filter(function ($attendance) {
                return $attendance['check_in'] && Carbon::parse($attendance['check_in'])->format('H:i:s') > '10:00:00';
            })->count(),
            'absent' => $attendances->filter(function ($attendance) {
                return $attendance['check_in'] === null;
            })->count()
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Attendance data fetched successfully',
            'data' => [
                'attendances' => $attendances->values(),
                'monthly_stats' => $monthlyStats
            ]
        ]);
    }
    public function show(Request $request, $id)
    {
        $attendance = StaffAttendence::find($id);

        if (!$attendance) {
            return response()->json([
                'status' => 'error',
                'message' => 'No attendance record found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Attendance record fetched successfully',
            'data' => $attendance
        ]);
    }
    public function todayAttendance(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();

        $attendance = StaffAttendence::where('staff_id', $user->id)
            ->whereDate('created_at', $today)
            ->first();
        if (!$attendance) {
            $attendance = StaffAttendence::create([
                'staff_id' => $user->id,
                'created_at' => now()
            ]);
        }
        return response()->json(
            $attendance
        );
    }

    public function checkIn(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'location' => 'required|string',
            'longitude' => 'required|numeric',
            'latitude' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $today = now()->toDateString();

        // Check if attendance already exists for today
        $existingAttendance = StaffAttendence::where('staff_id', $user->id)
            ->whereDate('created_at', $today)
            ->first();

        if ($existingAttendance) {
            if ($existingAttendance->check_in) {
                return response()->json([
                    'status' => "error",
                    'message' => 'Already checked in for today'
                ], 422);
            }

            $existingAttendance->update([
                'check_in' => now(),
                'check_in_location' => $request->input('location'),
                'check_in_longitude' => $request->input('longitude'),
                'check_in_latitude' => $request->input('latitude'),
            ]);

            return response()->json([
                'status' => "success",
                'message' => 'Check in successful'
            ]);
        }

        $attendance = StaffAttendence::create([
            'staff_id' => $user->id,
            'check_in' => now(),
            'check_in_location' => $request->input('location'),
            'check_in_longitude' => $request->input('longitude'),
            'check_in_latitude' => $request->input('latitude'),
        ]);
        $title = "New Notification";
        $message = $user->full_name . "has been checked In!";
        $status = "info";

        event(new NewNotification($title, $message, $status));
        return response()->json([
            'status' => "success",
            'message' => 'Check in successful'
        ]);
    }

    public function checkOut(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'location' => 'required|string',
            'longitude' => 'required|numeric',
            'latitude' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => "error",
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $today = now()->toDateString();
        $attendance = StaffAttendence::where('staff_id', $user->id)
            ->whereDate('created_at', $today)
            ->first();

        if (!$attendance) {
            return response()->json([
                'status' => "error",
                'message' => 'Please check in first'
            ], 422);
        }

        if ($attendance->check_out) {
            return response()->json([
                'status' => "error",
                'message' => 'Already checked out for today'
            ], 422);
        }

        if (!$attendance->check_in) {
            return response()->json([
                'status' => "error",
                'message' => 'Please check in first'
            ], 422);
        }

        $attendance->update([
            'check_out' => now(),
            'check_out_location' => $request->input('location'),
            'check_out_longitude' => $request->input('longitude'),
            'check_out_latitude' => $request->input('latitude'),
        ]);
        $title = "New Notification";
        $message = $user->full_name . "has been checked Out!";
        $status = "info";

        event(new NewNotification($title, $message, $status));
        return response()->json([
            'status' => "success",
            'message' => 'Check out successful'
        ]);
    }

    public function getMonthlyStats(Request $request)
    {
        $user = $request->user();
        $endOfMonth = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();
        $startOfMonth = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : now()->startOfMonth();
        $totalDays = intval($startOfMonth->diffInDays($endOfMonth) + 1);

        // Get all attendance records for current month
        $attendances = StaffAttendence::where('staff_id', $user->id)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->get();

        // Count present days (has check in and check out)
        $presentDays = intval($attendances->filter(function ($attendance) {
            return $attendance->check_in && $attendance->check_out;
        })->count());

        // Count late days (check in time after 9 AM)
        $lateDays = intval($attendances->filter(function ($attendance) {
            if (!$attendance->check_in) {
                return false;
            }
            return Carbon::parse($attendance->check_in)->format('H:i') > '09:00';
        })->count());

        // Count absent days (no check in or check out)
        $absentDays = intval($totalDays - $presentDays);

        return response()->json([
            'status' => "success",
            'message' => 'Attendance stats fetched successfully',
            'stats' => [
                'present' => $presentDays,
                'absent' => $absentDays,
                'late' => $lateDays,
                'total' => $totalDays,
            ]
        ]);
    }

    public function attendenceByUser(Request $request, $id)
    {
        // Set default dates if not provided in request
        $startDate = $request->has('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $endDate = $request->has('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();

        $filter = $request->input('filter', 'all');

        // Get staff details including salary
        $staff = Staff::findOrFail($id);
        $baseSalary = $staff->salary;
        $workingDaysInMonth = 30;
        $dailySalary = $baseSalary / $workingDaysInMonth; // Calculate daily salary based on working days

        // Get existing attendance records first
        $query = StaffAttendence::where('staff_id', $id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc');

        if ($filter !== 'all') {
            if ($filter === 'present') {
                $query->whereNotNull('check_in');
            } elseif ($filter === 'late') {
                $query->whereNotNull('check_in')
                    ->whereRaw("TIME(check_in) > '10:00:00'");
            } elseif ($filter === 'absent') {
                $query->whereNull('check_in');
            }
        }

        $existingAttendances = $query->get();

        // Generate array of all dates in range
        $period = Carbon::parse($startDate)->daysUntil($endDate);
        $allDates = [];
        foreach ($period as $date) {
            $allDates[] = $date->format('Y-m-d');
        }

        // Map attendance records for all dates
        $attendances = collect($allDates)->map(function ($date) use ($existingAttendances, $id, $dailySalary) {
            // Check if date is Sunday
            $isSunday = Carbon::parse($date)->isSunday();

            // Find existing attendance for this date
            $existing = $existingAttendances->first(function ($attendance) use ($date) {
                return Carbon::parse($attendance->created_at)->format('Y-m-d') === $date;
            });

            if ($isSunday) {
                return [
                    'id' => null,
                    'staff_id' => $id,
                    'check_in' => null,
                    'check_in_location' => null,
                    'check_in_longitude' => null,
                    'check_in_latitude' => null,
                    'check_out' => null,
                    'check_out_location' => null,
                    'check_out_longitude' => null,
                    'check_out_latitude' => null,
                    'created_at' => Carbon::parse($date)->format('Y-m-d\TH:i:s.u\Z'),
                    'updated_at' => null,
                    'is_sunday' => true,
                    'daily_salary' => $dailySalary // Full salary for Sundays
                ];
            }

            if ($existing) {
                return [
                    'id' => $existing->id,
                    'staff_id' => $existing->staff_id,
                    'check_in' => $existing->check_in,
                    'check_in_location' => $existing->check_in_location,
                    'check_in_longitude' => $existing->check_in_longitude,
                    'check_in_latitude' => $existing->check_in_latitude,
                    'check_out' => $existing->check_out,
                    'check_out_location' => $existing->check_out_location,
                    'check_out_longitude' => $existing->check_out_longitude,
                    'check_out_latitude' => $existing->check_out_latitude,
                    'created_at' => Carbon::parse($date)->format('Y-m-d\TH:i:s.u\Z'),
                    'updated_at' => $existing->updated_at,
                    'is_sunday' => false,
                    'daily_salary' => $existing->check_in ? $dailySalary : 0 // Full salary if present
                ];
            }

            // Create virtual attendance record for missing date
            return [
                'id' => null,
                'staff_id' => $id,
                'check_in' => null,
                'check_in_location' => null,
                'check_in_longitude' => null,
                'check_in_latitude' => null,
                'check_out' => null,
                'check_out_location' => null,
                'check_out_longitude' => null,
                'check_out_latitude' => null,
                'created_at' => Carbon::parse($date)->format('Y-m-d\TH:i:s.u\Z'),
                'updated_at' => null,
                'is_sunday' => false,
                'daily_salary' => 0 // No salary for absent days
            ];
        });

        // Calculate monthly stats, excluding Sundays for working day counts
        $workingDays = $attendances->filter(function ($attendance) {
            return !$attendance['is_sunday'];
        });

        $presentDays = $workingDays->filter(function ($attendance) {
            return $attendance['check_in'] !== null;
        })->count();

        $absentDays = $workingDays->filter(function ($attendance) {
            return $attendance['check_in'] === null;
        })->count();

        $sundays = $attendances->filter(function ($attendance) {
            return $attendance['is_sunday'];
        })->count();

        // Calculate total salary based on present days and Sundays
        $totalSalary = ($presentDays + $sundays) * $dailySalary;

        $monthlyStats = [
            'total_days' => $workingDays->count(),
            'present' => $presentDays,
            'absent' => $absentDays,
            'sundays' => $sundays,
            'total_salary' => $totalSalary,
            'base_salary' => $baseSalary
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Attendance data fetched successfully',
            'data' => [
                'attendances' => $attendances->values(),
                'monthly_stats' => $monthlyStats
            ]
        ]);
    }
}
