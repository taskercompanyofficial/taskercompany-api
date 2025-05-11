<?php

namespace App\Http\Controllers\Crm\Authenticated;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
          public function getComplaints(){
            $complaints = Complaint::with('user', 'brand', 'branch', 'technician')->get();
            return $complaints;
        }
    public function complaintByStatus(Request $request)
    {
        // Get the `from` and `to` parameters from the request
        $from = $request->input('from');
        $to = $request->input('to');

        // For counts: Use today's date range if no dates provided
        $countFromDate = $from ? Carbon::parse($from) : now()->startOfDay();
        $countToDate = $to ? Carbon::parse($to) : now()->endOfDay();

        // For chart data: Always use last 7 days
        $chartFromDate = now()->subDays(7)->startOfDay();
        $chartToDate = now()->endOfDay();

        // Get previous period for trend calculation
        $previousFromDate = (clone $countFromDate)->subDays(7);
        $previousToDate = (clone $countToDate)->subDays(7);

        // Helper function to calculate trend percentage
        $calculateTrend = function ($current, $previous) {
            if ($previous == 0)
                return $current > 0 ? '+100%' : '0%';
            $change = (($current - $previous) / $previous) * 100;
            return ($change >= 0 ? '+' : '') . number_format($change, 1) . '%';
        };

        // Current period counts (today by default or date range if provided)
        $openedCount = Complaint::where('status', 'open')
            ->whereBetween('created_at', [$countFromDate, $countToDate])
            ->count();

        $closedCount = Complaint::whereIn('status', ['closed', 'pending-amount', 'completed', 'cancelled', 'open', 'feedback-pending'])
            ->whereBetween('updated_at', [$countFromDate, $countToDate])
            ->count();

        $rejectedCount = Complaint::where('status', 'cancelled')
            ->whereBetween('updated_at', [$countFromDate, $countToDate])
            ->count();

        $pendingCount = Complaint::whereNotIn('status', ['closed', 'pending-amount', 'completed', 'cancelled', 'open', 'feedback-pending', 'pending-by-brand'])
            ->whereDate('created_at', Carbon::today())
            ->count();

        $totalCount = Complaint::whereBetween('created_at', [$countFromDate, $countToDate])->count();

        // Previous period counts for trend calculation (only for closed, rejected and total)
        $previousClosedCount = Complaint::whereIn('status', ['closed', 'pending-amount', 'completed', 'cancelled', 'open', 'feedback-pending', 'pending-by-brand'])
            ->whereBetween('updated_at', [$previousFromDate, $previousToDate])
            ->count();
        $previousRejectedCount = Complaint::where('status', 'cancelled')
            ->whereBetween('updated_at', [$previousFromDate, $previousToDate])
            ->count();
        $previousTotalCount = Complaint::whereBetween('created_at', [$previousFromDate, $previousToDate])->count();

        // Get historical data for last 7 days regardless of date selection
        $getHistoricalData = function ($status) use ($chartFromDate, $chartToDate) {
            $dates = [];
            $current = clone $chartFromDate;

            // Create array of all dates in range
            while ($current <= $chartToDate) {
                $dates[$current->format('Y-m-d')] = [
                    'date' => $current->format('Y-m-d'),
                    'count' => 0
                ];
                $current->addDay();
            }

            // Get actual data
            $data = Complaint::where('status', $status)
                ->whereBetween('created_at', [$chartFromDate, $chartToDate])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->get()
                ->keyBy('date')
                ->toArray();

            // Merge actual data with zero-filled dates
            foreach ($data as $date => $record) {
                $dates[$date]['count'] = $record['count'];
            }

            return array_values($dates);
        };


        // Get total historical data
        $getTotalHistoricalData = function () use ($chartFromDate, $chartToDate) {
            $dates = [];
            $current = clone $chartFromDate;

            while ($current <= $chartToDate) {
                $dates[$current->format('Y-m-d')] = [
                    'date' => $current->format('Y-m-d'),
                    'count' => 0
                ];
                $current->addDay();
            }

            $data = Complaint::whereBetween('created_at', [$chartFromDate, $chartToDate])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->get()
                ->keyBy('date')
                ->toArray();

            foreach ($data as $date => $record) {
                $dates[$date]['count'] = $record['count'];
            }

            return array_values($dates);
        };

        $statuses = [
            "open_and_pending" => [
                'opened' => [
                    'count' => $openedCount,
                ],
                'in-progress' => [
                    'count' => $pendingCount,
                ]
            ],
            "others" => [

                'closed' => [
                    'count' => $closedCount,
                    'trend' => $calculateTrend($closedCount, $previousClosedCount),
                    'data' => $getHistoricalData('closed')
                ],
                'rejected' => [
                    'count' => $rejectedCount,
                    'trend' => $calculateTrend($rejectedCount, $previousRejectedCount),
                    'data' => $getHistoricalData('cancelled')
                ],
                'total' => [
                    'count' => $totalCount,
                    'trend' => $calculateTrend($totalCount, $previousTotalCount),
                    'data' => $getTotalHistoricalData()
                ]
            ]
        ];

        return response()->json($statuses);
    }

public function getStatusChartData()
{
    // Define statuses to exclude from "In Progress"
    $excludedStatuses = ['closed',  'feedback-pending', 'cancelled'];
    
    // Get unique statuses and their counts
    $currentMonthData = Complaint::selectRaw('status, COUNT(*) as count')
        ->groupBy('status')
        ->get();

    // Format data
    $statusData = [];
    $inProgressCount = 0;
    
    foreach ($currentMonthData as $current) {
        // Store actual status counts
        $statusData[$current->status] = [
            'count' => $current->count,
        ];
        
        // Sum all non-excluded statuses into "In Progress"
        if (!in_array($current->status, $excludedStatuses)) {
            $inProgressCount += $current->count;
        }
    }
    
    // Add 'In Progress' category
    if ($inProgressCount > 0) {
        $statusData['In Progress'] = [
            'count' => $inProgressCount,
        ];
    }

    return response()->json($statusData);
}






   public function getComplaintStatusByBrand()
{
    // Get all installation-related complaint types
    $installationTypes = Complaint::distinct('complaint_type')
        ->where('complaint_type', 'like', '%installation%')
        ->pluck('complaint_type')
        ->toArray();

    // Define status constants
    $completedStatuses = ['completed', 'cancelled', 'feedback-pending', 'closed', 'amount-pending', 'pending-by-brand'];
    $openStatus = 'open';
    $feedbackPendingStatus = 'feedback-pending';
    $pendingStatus = 'pending-by-brand';
    $holdStatuses = Complaint::distinct('status')
        ->where('status', 'like', '%hold%')
        ->pluck('status')
        ->toArray();

    // Base query for installation complaints
    $baseQuery = Complaint::whereIn('complaint_type', $installationTypes);

    // Get various status counts
    $totalInstallations = (clone $baseQuery)
        ->whereNotIn('status', array_merge($completedStatuses, $holdStatuses))
        ->count();

    $feedbackPendingCount = (clone $baseQuery)
        ->where('status', $feedbackPendingStatus)
        ->count();

    $pendingCount = (clone $baseQuery)
        ->where('status', $pendingStatus)
        ->count();

    $holdStatusCounts = (clone $baseQuery)
        ->whereIn('status', $holdStatuses)
        ->count();

    // Get brand-wise installation data, excluding hold statuses
    $brandInstallations = (clone $baseQuery)
        ->whereNotIn('status', array_merge($completedStatuses, $holdStatuses)) // Exclude hold statuses
        ->with(['brand' => function ($query) {
            $query->select('id', 'name');
        }])
        ->get()
        ->groupBy('brand_id')
        ->map(function ($complaints, $brandId) use ($openStatus, $completedStatuses) {
            $brand = $complaints->first()->brand;
            $brandName = $brand ? $brand->name : 'Unknown';
            $brandId = $brand ? $brand->id : $brandId;

            $openCount = $complaints->where('status', $openStatus)->count();
            $inProgressCount = $complaints
                ->whereNotIn('status', array_merge([$openStatus], $completedStatuses))
                ->count();

            return [
                'brand' => $brandName,
                'brand_id' => $brandId,
                'total_count' => $complaints->count(),
                'open_count' => $openCount,
                'in_progress' => $inProgressCount
            ];
        })
        ->values();

    // Prepare response data
    $response = [
        'overview' => [
            'total_open_installations' => [
                'count' => $totalInstallations,
                'label' => 'Total Open Installations',
                'color' => '#8884d8'
            ],
        ],
        'total_open_installations' => [
            'count' => $totalInstallations,
            'label' => 'Total Open Installations',
            'color' => '#8884d8'
        ],
        'feedback_pending' => [
            'count' => $feedbackPendingCount,
            'label' => 'Feedback Pending',
            'color' => '#ffcd56'
        ],
        'pending_by_brand' => [
            'count' => $pendingCount,
            'label' => 'Pending by Brand',
            'color' => '#4bc0c0'
        ],
        'on_hold' => [
            'count' => $holdStatusCounts,
            'label' => 'On Hold',
            'color' => '#9966ff'
        ],
        'brand_data' => $brandInstallations
    ];

    return response()->json($response);
}

}
