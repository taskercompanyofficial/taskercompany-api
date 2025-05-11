<?php

namespace App\Http\Controllers\Crm\Authenticated;

use App\Http\Controllers\Controller;
use App\Models\SubService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class SubServiceController extends Controller
{
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
        $service_id = $request->input('service_id');

        // Build query with filters
        $subServicesQuery = SubService::query()
            ->when($q, fn($query) => $query->where('name', 'like', "%$q%"))
            ->when($status, fn($query) => $query->where('status', $status))
            ->when($category_id, fn($query) => $query->where('category_id', $category_id))
            ->when($service_id, fn($query) => $query->where('service_id', $service_id))
            ->orderByDesc('created_at');

        // Paginate results
        $subServices = $subServicesQuery->paginate($perPage, ['*'], 'page', $page);

        // Load relationships
        $subServices->load(['category:id,name', 'service:id,name']);

        // Transform the collection
        $subServices->getCollection()->transform(function ($subService) {
            $subService->category_name = $subService->category->name;
            $subService->service_name = $subService->service->name;
            unset($subService->category, $subService->service);
            return $subService;
        });

        // Return JSON response
        return response()->json([
            'data' => $subServices->items(),
            'pagination' => [
                'current_page' => $subServices->currentPage(),
                'last_page' => $subServices->lastPage(),
                'per_page' => $subServices->perPage(),
                'total' => $subServices->total(),
                'next_page' => $subServices->currentPage() < $subServices->lastPage() ? $subServices->currentPage() + 1 : null,
                'prev_page' => $subServices->currentPage() > 1 ? $subServices->currentPage() - 1 : null,
            ],
        ]);
    }
    public function store(Request $request)
    {

        // Validate the incoming request
        $validatedData = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'service_id' => 'required|exists:services,id',
            'name' => 'required|string|max:255|unique:sub_services,name',
            'description' => 'required|string|max:250',
            'price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'keywords' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
            'hero_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
        ]);
        try {
            $imagePath = null;
            $heroImagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('images/sub-services', 'public');
            }
            if ($request->hasFile('hero_image')) {
                $heroImagePath = $request->file('hero_image')->store('images/sub-services', 'public');
            }
            $subService = new SubService();
            $subService->unique_id = Str::uuid();
            $subService->category_id = $validatedData['category_id'];
            $subService->service_id = $validatedData['service_id'];
            $subService->name = $validatedData['name'];
            $subService->slug = Str::slug($validatedData['name'], '-');
            $subService->description = $validatedData['description'];
            $subService->keywords = $validatedData['keywords'];
            $subService->status = $validatedData['status'];
            $subService->image = $imagePath;
            $subService->hero_image = $heroImagePath;
            $subService->price = $validatedData['price'];
            $subService->discount = $validatedData['discount'];
            $subService->save();
            return response()->json([
                "status" => "success",
                "message" => "Sub Service has been created",
                "data" => $subService,
            ], 200);

        } catch (\Exception $err) {
            // Log the error and return a response
            Log::error("Error creating Sub Service: " . $err->getMessage(), [
                'stack' => $err->getTraceAsString(),
            ]);
            return response()->json([
                "status" => "error",
                "mesage" => "Error creating Sub Service: " . $err->getMessage(),
            ], 500);
        }

    }
    public function show(string $slug)
    {
        $subService = SubService::where('slug', $slug)->first();
        return $subService;
    }
    public function update(Request $request, $slug)
    {
        // Find the service first
        $subService = SubService::where('slug', $slug)->firstOrFail();

        // Validate the incoming request
        $validatedData = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255|unique:sub_services,name,' . $subService->id,
            'description' => 'required|string|max:250',
            'keywords' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
            'hero_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
        ]);

        try {
            // Store the current paths (to avoid overwriting if no new image is uploaded)
            $imagePath = $subService->image; // Default current image path
            $heroImagePath = $subService->hero_image; // Default current hero image path

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
            $subService->category_id = $validatedData['category_id'];
            $subService->name = $validatedData['name'];
            $subService->slug = Str::slug($validatedData['name'], '-');
            $subService->description = $validatedData['description'];
            $subService->keywords = $validatedData['keywords'];
            $subService->status = $validatedData['status'];
            $subService->image = $imagePath; // Only updated if a new image is uploaded
            $subService->hero_image = $heroImagePath; // Only updated if a new hero image is uploaded
            $subService->save();

            return response()->json([
                "status" => "success",
                "message" => "Sub Service has been updated",
                "data" => $subService,
            ], 200);

        } catch (\Exception $err) {
            // Log the error and return a response
            Log::error("Error updating Sub Service: " . $err->getMessage(), [
                'stack' => $err->getTraceAsString(),
            ]);
            return response()->json([
                "status" => "error",
                "message" => "Error updating Sub Service: " . $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $slug)
    {

        try {
            $subService = SubService::where("slug", $slug)->first();

            if (!$subService) {
                return response()->json([
                    "status" => "error",
                    "mesage" => "Sub Service not found ",
                ], 5402);
            }
            Storage::disk('public')->delete($subService->image);
            Storage::disk('public')->delete($subService->hero_image);
            $subService->delete();

            return response()->json([
                "status" => "success",
                "message" => "Sub Service has been Deleted",
            ], 200);


        } catch (\Exception $err) {
            // Log the error and return a response
            Log::error("Error deleting Sub Service: " . $err->getMessage(), [
                'stack' => $err->getTraceAsString(),
            ]);
            return response()->json([
                "status" => "error",
                "mesage" => "Error deleting Sub Service: " . $err->getMessage(),
            ], 500);
        }
    }
}
