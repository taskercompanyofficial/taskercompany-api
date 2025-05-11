<?php

namespace App\Http\Controllers\Crm\Authenticated;
use App\Models\Complaint;
use Carbon\Carbon;

use App\Http\Controllers\Controller;
use App\Models\Branches;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BranchesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        // Parse and validate date range inputs
        $from = $request->input('from');
        $to = $request->input('to');
        $start_date = $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->startOfDay();
        $chart_start_date = $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->subDays(7)->startOfDay();
        $end_date = $to ? Carbon::parse($to)->endOfDay() : Carbon::now()->endOfDay();

        // Get pagination and filter parameters
        $perPage = $request->input('per_page', 50);
        $page = $request->input('page', 1);
        $q = $request->input('q', '');
        $status = $request->input('status', '');

        // Build branches query with filters
        $branchesQuery = Branches::query()
            ->when($q, fn($query) => $query->where('name', 'like', "%$q%"))
            ->when($status, fn($query) => $query->where('status', $status))
            ->with(['branch']) // Eager load branch relationship
            ->orderByDesc('created_at');

        // Total count for pagination
        $total = $branchesQuery->count();

        // Paginate branches with related complaints counts
        $branches = $branchesQuery->paginate($perPage, ['*'], 'page', $page)
            ->map(function ($branch) {
                // Fetch aggregated complaint counts in a single query
                $complaintCounts = Complaint::selectRaw("
                    branch_id,
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open,
                    SUM(CASE WHEN status = 'closed' AND DATE(updated_at) = CURDATE() THEN 1 ELSE 0 END) AS closed_today,
                    SUM(CASE WHEN status NOT IN ('open', 'closed') THEN 1 ELSE 0 END) AS others
                ")
                    ->where('branch_id', $branch->unique_id)
                    ->groupBy('branch_id')
                    ->first();

                $branch->branch_manager = $branch->branch?->username;
                $branch->open = $complaintCounts->open ?? 0;
                $branch->closed_today = $complaintCounts->closed_today ?? 0;
                $branch->others = $complaintCounts->others ?? 0;

                return $branch;
            });

        // Prepare chart data
        $chartData = $branches->map(function ($branch) {
            // Calculate complaint counts
            $open = Complaint::where('branch_id', $branch->id)->where('status', 'open')->count();
            $closed = Complaint::where('branch_id', $branch->id)->where('status', 'closed')->count();
            $other = Complaint::where('branch_id', $branch->id)
                ->whereNotIn('status', ['open', 'closed'])
                ->count();

            return [
                'branch_name' => $branch->name,
                'open' => $open ?? 0,
                'closed' => $closed ?? 0,
                'other' => $other ?? 0,
            ];
        });

        // Prepare custom pagination data
        $paginationData = [
            'current_page' => $page,
            'last_page' => ceil($total / $perPage),
            'first_page' => 1,
            'per_page' => $perPage,
            'total' => $total,
            'next_page' => ($page < ceil($total / $perPage)) ? $page + 1 : null,
            'prev_page' => ($page > 1) ? $page - 1 : null,
        ];

        // Return JSON response
        return response()->json([
            'data' => $branches,
            'chart_data' => $chartData,
            'pagination' => $paginationData,
        ]);
    }




    /**
     * Store a newly created resource in storage.
     */

    public function store(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'branch_contact_no' => 'required|string|max:255',
            'branch_address' => 'required|string|max:255',
            'status' => 'required|string|in:active,inactive',
            'image' => 'nullable|image|max:2048',
        ]);
        try {
            // Handle image upload if files are present
            if ($request->hasFile(key: 'image')) {
                $imagePath = $request->file('image')->store('images/branches', 'public');
            }
            // Prepare data for saving
            $branch = Branches::create([
                'name' => $validated['name'],
                'unique_id' => Str::slug($validated['name'], '-'),
                'branch_contact_no' => $validated['branch_contact_no'],
                'branch_address' => $validated['branch_address'],
                'images' => $imagePath ?? null, // Save images as JSON in the database
            ]);

            return response()->json([
                "status" => "success",
                "message" => "Branch has been created",
            ], 200);

        } catch (\Exception $err) {
            // Log the error and return a response
            Log::error("Error creating Branch: " . $err->getMessage(), [
                'stack' => $err->getTraceAsString(),
            ]);
            return response()->json([
                "status" => "error",
                "mesage" => "Error creating Branch: " . $err->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, string $unique_id)
    {
        // Validate the incoming request
        $request->validate([
            'name' => 'required|string|max:255',
            'branch_contact_no' => 'required|string|max:15',
            'branch_address' => 'required|string|max:500',
            'status' => 'required|string|in:active,inactive',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        try {
            // Find the branch using the unique_id
            $branch = Branches::findOrFail($unique_id);

            // Update the branch attributes
            $branch->name = $request->input('name');
            $branch->branch_contact_no = $request->input('branch_contact_no');
            $branch->branch_address = $request->input('branch_address');
            $branch->status = $request->input('status');

            // Handle image upload if there is one
            if ($request->hasFile('image')) {
                // Delete the old image if it exists
                if ($branch->image && Storage::exists($branch->image)) {
                    Storage::delete($branch->image);
                }

                // Store the new image and update the 'image' field
                $imagePath = $request->file('image')->store('branches/images', 'public');
                $branch->image = $imagePath;
            }

            // Save the updated branch
            $branch->save();

            // Return a success response
            return response()->json([
                "status" => "success",
                "message" => "Branch has been updated successfully.",
            ], 200);

        } catch (\Exception $err) {
            // Log the error for debugging
            Log::error("Error updating Branch: " . $err->getMessage(), [
                'stack' => $err->getTraceAsString(),
            ]);

            // Return an error response
            return response()->json([
                "status" => "error",
                "message" => "Failed to update branch. Please try again.",
                "error" => $err->getMessage(),
            ], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $branch = Branches::destroy($id);
            if (!$branch) {
                return response()->json([
                    "status" => "error",
                    "mesage" => "Branch not found ",
                ], 5402);
            }
            return response()->json([
                "status" => "success",
                "message" => "Branch has been Deleted",
            ], 200);


        } catch (\Exception $err) {
            // Log the error and return a response
            Log::error("Error deleting branch: " . $err->getMessage(), [
                'stack' => $err->getTraceAsString(),
            ]);
            return response()->json([
                "status" => "error",
                "mesage" => "Error deleting branch: " . $err->getMessage(),
            ], 500);
        }
    }
}
