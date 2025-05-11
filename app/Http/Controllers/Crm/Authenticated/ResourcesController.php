<?php

namespace App\Http\Controllers\Crm\Authenticated;

use App\Http\Controllers\Controller;
use App\Models\Branches;
use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Str;

class ResourcesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $perPage = $request->input('per_page', 50);
        $page = $request->input('page', 1);
        $searchQuery = $request->input('q', '');
        $statusFilter = $request->input('status', '');
        $branchFilter = $request->input('branch_id', '');
        $resourceQuery = Resource::query();

        if ($searchQuery) {
            // Adjust field name to 'contact_name' if searching by contact name
            $resourceQuery->where('name', $searchQuery);
        }
        if ($statusFilter) {
            $resourceQuery->where('status', $statusFilter);
        }
        if ($branchFilter) {
            $resourceQuery->where('branch_id', $branchFilter);
        }
        $totalResorurces = $resourceQuery->count();

        // Fetch paginated complaints with proper associations and formatted response
        $resources = $resourceQuery->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($resource) {
                // Fetch the associated brand name if it exists
                $resource->branch_name = $resource->branch ? $resource->branch->name : null;
                $resource->username = $resource->user ? $resource->user->username : null;
                return $resource;
            });
        $paginationData = [
            'current_page' => $page,
            'last_page' => ceil($totalResorurces / $perPage),
            'first_page' => 1,
            'per_page' => $perPage,
            'total' => $totalResorurces,
            'next_page' => ($page < ceil($totalResorurces / $perPage)) ? $page + 1 : null,
            'prev_page' => ($page > 1) ? $page - 1 : null,
        ];

        return response()->json([
            'data' => $resources,
            'pagination' => $paginationData,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $payload = $request->validate([
            'name' => 'required|string',
            'price' => 'nullable|string',
            'quantity' => 'nullable|string',
            'branch_id' => 'nullable|exists:branches,unique_id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Only one image
        ]);
        $user = Auth::user();
        $payload['user_id'] = $user->unique_id;
        $payload['unique_id'] = Str::uuid();
        if ($user->role !== 'admin') {
            $payload['branch_id'] = $user->branch_id;
        }
        // Assuming you need to save the resource to the database
        $resource = new Resource($payload);
        $resource->save();

        // Handle the image upload logic (single image)
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imagePath = $image->store('resources_images', 'public'); // Store image in a public folder
            // Optionally, you can save the image path in the database
            $resource->images()->create(['path' => $imagePath]);
        }
        return redirect()
            ->route('resources.index')
            ->with('success', 'Resouce created successfully!');
    }



    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
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
}
