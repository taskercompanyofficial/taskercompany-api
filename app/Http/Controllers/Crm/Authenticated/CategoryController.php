<?php
namespace App\Http\Controllers\Crm\Authenticated;


use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryController extends Controller
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

        // Build query with filters
        $categoryQuery = Category::query()
            ->when($q, fn($query) => $query->where('name', 'like', "%$q%"))
            ->when($status, fn($query) => $query->where('status', $status))
            ->orderByDesc('created_at');

        // Paginate results
        $categories = $categoryQuery->paginate($perPage, ['*'], 'page', $page);

        // Fetch aggregated complaint counts
        $complaintCounts = Complaint::selectRaw("
            category_id,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS closed,
            SUM(CASE WHEN status NOT IN ('open', 'closed') THEN 1 ELSE 0 END) AS other
        ")
            ->whereIn('category_id', $categories->pluck('unique_id'))
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');

        // Attach aggregated data to categories
        $categories->getCollection()->transform(function ($category) use ($complaintCounts) {
            $counts = $complaintCounts->get($category->unique_id, (object) ['open' => 0, 'closed' => 0, 'other' => 0]);
            $category->open = $counts->open;
            $category->closed = $counts->closed;
            $category->others = $counts->other;
            return $category;
        });

        // Prepare chart data
        $chartData = $categories->getCollection()->map(function ($category) {
            return [
                'category_name' => $category->name,
                'open' => $category->open,
                'closed' => $category->closed,
                'other' => $category->others,
            ];
        });

        // Return JSON response
        return response()->json([
            'data' => $categories->items(), // Use items() to get the paginated data as an array
            'chart_data' => $chartData,
            'pagination' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
                'next_page' => $categories->currentPage() < $categories->lastPage() ? $categories->currentPage() + 1 : null,
                'prev_page' => $categories->currentPage() > 1 ? $categories->currentPage() - 1 : null,
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
            'name' => 'required|string|max:255|unique:services,name',
            'description' => 'required|string|max:250',
            'keywords' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
            'hero_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
        ]);
        try {
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('images/categories', 'public');
            }
            if ($request->hasFile('hero_image')) {
                $heroImagePath = $request->file('hero_image')->store('images/categories', 'public');
            }
            $category = new Category();
            $category->unique_id = Str::uuid();
            $category->name = $validatedData['name'];
            $category->slug = Str::slug($validatedData['name'], '-');
            $category->description = $validatedData['description'];
            $category->keywords = $validatedData['keywords'];
            $category->status = $validatedData['status'];
            $category->image = $imagePath;
            $category->hero_image = $heroImagePath;
            $category->save();
            return response()->json([
                "status" => "success",
                "message" => "Category has been created",
                "data" => $category,
            ], 200);

        } catch (\Exception $err) {
            // Log the error and return a response
            Log::error("Error creating Category: " . $err->getMessage(), [
                'stack' => $err->getTraceAsString(),
            ]);
            return response()->json([
                "status" => "error",
                "mesage" => "Error creating Category: " . $err->getMessage(),
            ], 500);
        }

    }

    /**
     * Display the specified resource.
     */
    public function show(string $slug)
    {
        $category = Category::where('slug', $slug)->first();
        return $category;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $slug)
    {
        $category = Category::where('slug', $slug)->first();

        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'required|string|max:250',
            'keywords' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
            'hero_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
        ]);

        try {
            // Store the current paths (to avoid overwriting if no new image is uploaded)
            $imagePath = $category->image; // Default current image path
            $heroImagePath = $category->hero_image; // Default current hero image path

            // Handle image upload if it exists
            if ($request->hasFile('image')) {
                // Delete the old image if it exists
                if ($imagePath) {
                    Storage::disk('public')->delete($imagePath);
                }
                // Store the new image and update the image path
                $imagePath = $request->file('image')->store('images/categories', 'public');
            }

            // Handle hero image upload if it exists
            if ($request->hasFile('hero_image')) {
                // Delete the old hero image if it exists
                if ($heroImagePath) {
                    Storage::disk('public')->delete($heroImagePath);
                }
                // Store the new hero image and update the hero image path
                $heroImagePath = $request->file('hero_image')->store('images/categories', 'public');
            }

            // Update category data
            $category->name = $validatedData['name'];
            $category->slug = Str::slug($validatedData['name'], '-');
            $category->description = $validatedData['description'];
            $category->keywords = $validatedData['keywords'];
            $category->status = $validatedData['status'];
            $category->image = $imagePath; // Only updated if a new image is uploaded
            $category->hero_image = $heroImagePath; // Only updated if a new hero image is uploaded
            $category->save();

            return response()->json([
                "status" => "success",
                "message" => "Category has been updated",
                "data" => $category,
            ], 200);

        } catch (\Exception $err) {
            // Log the error and return a response
            Log::error("Error updating Category: " . $err->getMessage(), [
                'stack' => $err->getTraceAsString(),
            ]);
            return response()->json([
                "status" => "error",
                "message" => "Error updating Category: " . $err->getMessage(),
            ], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $slug)
    {

        try {
            $category = Category::where("slug", $slug)->first();

            if (!$category) {
                return response()->json([
                    "status" => "error",
                    "mesage" => "Category not found ",
                ], 5402);
            }
            Storage::disk('public')->delete($category->image);
            Storage::disk('public')->delete($category->hero_image);
            $category->delete();

            return response()->json([
                "status" => "success",
                "message" => "Category has been Deleted",
            ], 200);


        } catch (\Exception $err) {
            // Log the error and return a response
            Log::error("Error deleting Category: " . $err->getMessage(), [
                'stack' => $err->getTraceAsString(),
            ]);
            return response()->json([
                "status" => "error",
                "mesage" => "Error deleting Category: " . $err->getMessage(),
            ], 500);
        }
    }
}
