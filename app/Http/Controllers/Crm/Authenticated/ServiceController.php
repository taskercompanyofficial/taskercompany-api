<?php

namespace App\Http\Controllers\Crm\Authenticated;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ServiceController extends Controller
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
        $category_id = $request->input('category_id');

        // Build query with filters
        $servicesQuery = Services::query()
            ->when($q, fn($query) => $query->where('name', 'like', "%$q%"))
            ->when($status, fn($query) => $query->where('status', $status))
            ->when($category_id, fn($query) => $query->where('category_id', $category_id))
            ->orderByDesc('created_at');

        // Paginate results
        $services = $servicesQuery->paginate($perPage, ['*'], 'page', $page);

        // Load category relationship
        $services->load('category:id,name');

        // Fetch aggregated complaint counts
        $complaintCounts = Complaint::selectRaw("
            service_id,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS closed,
            SUM(CASE WHEN status NOT IN ('open', 'closed') THEN 1 ELSE 0 END) AS other
        ")
            ->whereIn('service_id', $services->pluck('unique_id'))
            ->groupBy('service_id')
            ->get()
            ->keyBy('service_id');

        // Attach aggregated data to services and transform
        $services->getCollection()->transform(function ($service) use ($complaintCounts) {
            $counts = $complaintCounts->get($service->unique_id, (object) ['open' => 0, 'closed' => 0, 'other' => 0]);
            $service->open = $counts->open;
            $service->closed = $counts->closed;
            $service->others = $counts->other;
            $service->category_name = $service->category->name;
            unset($service->category);
            return $service;
        });

        // Prepare chart data
        $chartData = $services->getCollection()->map(function ($service) {
            return [
                'service_name' => $service->name,
                'open' => $service->open,
                'closed' => $service->closed,
                'other' => $service->others,
            ];
        });

        // Return JSON response
        return response()->json([
            'data' => $services->items(),
            'chart_data' => $chartData,
            'pagination' => [
                'current_page' => $services->currentPage(),
                'last_page' => $services->lastPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
                'next_page' => $services->currentPage() < $services->lastPage() ? $services->currentPage() + 1 : null,
                'prev_page' => $services->currentPage() > 1 ? $services->currentPage() - 1 : null,
            ],
        ]);


    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        // Validate the incoming request
        $validatedData = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255|unique:services,name',
            'description' => 'required|string|max:250',
            'keywords' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
            'hero_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
        ]);
        try {
            $imagePath = null;
            $heroImagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('images/services', 'public');
            }
            if ($request->hasFile('hero_image')) {
                $heroImagePath = $request->file('hero_image')->store('images/services', 'public');
            }
            $service = new Services();
            $service->unique_id = Str::uuid();
            $service->category_id = $validatedData['category_id'];
            $service->name = $validatedData['name'];
            $service->slug = Str::slug($validatedData['name'], '-');
            $service->description = $validatedData['description'];
            $service->keywords = $validatedData['keywords'];
            $service->status = $validatedData['status'];
            $service->image = $imagePath;
            $service->hero_image = $heroImagePath;
            $service->save();
            return response()->json([
                "status" => "success",
                "message" => "Service has been created",
                "data" => $service,
            ], 200);

        } catch (\Exception $err) {
            // Log the error and return a response
            Log::error("Error creating Service: " . $err->getMessage(), [
                'stack' => $err->getTraceAsString(),
            ]);
            return response()->json([
                "status" => "error",
                "mesage" => "Error creating Service: " . $err->getMessage(),
            ], 500);
        }

    }


    /**
     * Display the specified resource.
     */
    public function show(string $slug)
    {
        $service = Services::where('slug', $slug)->first();
        return $service;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $slug)
    {
        // Find the service first
        $service = Services::where('slug', $slug)->firstOrFail();

        // Validate the incoming request
        $validatedData = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255|unique:services,name,' . $service->id,
            'description' => 'required|string|max:250',
            'keywords' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
            'hero_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
        ]);

        try {
            // Store the current paths (to avoid overwriting if no new image is uploaded)
            $imagePath = $service->image; // Default current image path
            $heroImagePath = $service->hero_image; // Default current hero image path

            // Handle image upload if it exists
            if ($request->hasFile('image')) {
                // Delete the old image if it exists
                if ($imagePath) {
                    Storage::disk('public')->delete($imagePath);
                }
                // Store the new image and update the image path
                $imagePath = $request->file('image')->store('images/services', 'public');
            }

            // Handle hero image upload if it exists
            if ($request->hasFile('hero_image')) {
                // Delete the old hero image if it exists
                if ($heroImagePath) {
                    Storage::disk('public')->delete($heroImagePath);
                }
                // Store the new hero image and update the hero image path
                $heroImagePath = $request->file('hero_image')->store('images/services', 'public');
            }

            // Update service data
            $service->category_id = $validatedData['category_id'];
            $service->name = $validatedData['name'];
            $service->slug = Str::slug($validatedData['name'], '-');
            $service->description = $validatedData['description'];
            $service->keywords = $validatedData['keywords'];
            $service->status = $validatedData['status'];
            $service->image = $imagePath; // Only updated if a new image is uploaded
            $service->hero_image = $heroImagePath; // Only updated if a new hero image is uploaded
            $service->save();

            return response()->json([
                "status" => "success",
                "message" => "Service has been updated",
                "data" => $service,
            ], 200);

        } catch (\Exception $err) {
            // Log the error and return a response
            Log::error("Error updating Service: " . $err->getMessage(), [
                'stack' => $err->getTraceAsString(),
            ]);
            return response()->json([
                "status" => "error",
                "message" => "Error updating Service: " . $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $slug)
    {

        try {
            $service = Services::where("slug", $slug)->first();

            if (!$service) {
                return response()->json([
                    "status" => "error",
                    "mesage" => "Service not found ",
                ], 5402);
            }
            Storage::disk('public')->delete($service->image);
            Storage::disk('public')->delete($service->hero_image);
            $service->delete();

            return response()->json([
                "status" => "success",
                "message" => "Service has been Deleted",
            ], 200);


        } catch (\Exception $err) {
            // Log the error and return a response
            Log::error("Error deleting Service: " . $err->getMessage(), [
                'stack' => $err->getTraceAsString(),
            ]);
            return response()->json([
                "status" => "error",
                "mesage" => "Error deleting Service: " . $err->getMessage(),
            ], 500);
        }
    }
}
