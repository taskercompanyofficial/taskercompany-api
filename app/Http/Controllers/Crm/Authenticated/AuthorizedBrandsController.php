<?php

namespace App\Http\Controllers\Crm\Authenticated;

use App\Http\Controllers\Controller;
use App\Models\AuthorizedBrands;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthorizedBrandsController extends Controller
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
        $brandsQuery = AuthorizedBrands::query()
            ->when($q, fn($query) => $query->where('name', 'like', "%$q%"))
            ->when($status, fn($query) => $query->where('status', $status))
            ->orderByDesc('created_at');

        // Total count for pagination
        $total = $brandsQuery->count();

        // Paginate branches with related complaints counts
        $brands = $brandsQuery->paginate($perPage, ['*'], 'page', $page)
            ->map(function ($brand) {
                // Fetch aggregated complaint counts in a single query
                $complaintCounts = Complaint::selectRaw("
                    brand_id,
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open,
                    SUM(CASE WHEN status = 'closed' AND DATE(updated_at) = CURDATE() THEN 1 ELSE 0 END) AS closed_today,
                    SUM(CASE WHEN status NOT IN ('open', 'closed') THEN 1 ELSE 0 END) AS others
                ")
                    ->where('brand_id', $brand->id)
                    ->groupBy('brand_id')
                    ->first();

                $brand->open = $complaintCounts->open ?? 0;
                $brand->closed_today = $complaintCounts->closed_today ?? 0;
                $brand->others = $complaintCounts->others ?? 0;

                return $brand;
            });

        // Prepare chart data
        $chartData = $brands->map(function ($brand) {
            // Calculate complaint counts
            $open = Complaint::where('brand_id', $brand->id)->where('status', 'open')->count();
            $closed = Complaint::where('brand_id', $brand->id)->where('status', 'closed')->count();
            $other = Complaint::where('brand_id', $brand->id)
                ->whereNotIn('status', ['open', 'closed'])
                ->count();

            return [
                'brand_name' => $brand->name,
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
            'data' => $brands,
            'chart_data' => $chartData,
            'pagination' => $paginationData,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'required|image|max:2048', // Validate the logo file
            'status' => 'required|string|in:active,inactive',
        ]);

        try {

            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('images/AuthorizedBrands', 'public');
            }
            // Save the brand to the database with the logo path
            AuthorizedBrands::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'image' => $imagePath ?? null,
                'status' => $request->status,
            ]);
            $user = Auth::user();

            return response()->json([
                "status" => "success",
                "message" => "Brand has been added",
            ], 200);
        } catch (\Exception $err) {
            // Log the error and return a response
            Log::error("Error creating Brand: " . $err->getMessage(), [
                'stack' => $err->getTraceAsString(),
            ]);
            return response()->json([
                "status" => "error",
                "mesage" => "Error creating Brand: " . $err->getMessage(),
            ], 500);
        }
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

        $request->validate([
            'name' => 'required|string|max:255|unique:authorized_brands,name,' . $id,
            'image' => 'nullable|image|max:2048',
            'status' => 'required|string|in:active,inactive',
        ]);
        // Find the brand by ID
        $brand = AuthorizedBrands::find($id);

        // Check if brand exists
        if (!$brand) {
            return response()->json(['message' => 'Brand not found'], 404);
        }

        // Validate the request data


        // Update the brand name
        $brand->name = $request->name;
        $brand->status = $request->status;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('images/AuthorizedBrands', 'public');
            $brand->image = $imagePath;
        }
        $brand->save();

        // Return a success response
        return response()->json([
            "status" => "success",
            "message" => "Brand has been updated",
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $delete = AuthorizedBrands::destroy($id);
        return response()->json([
            "status" => "success",
            "message" => "Brand has been deleted",
        ], 200);
    }
}
