<?php

namespace App\Http\Controllers\Authenticated;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Exception;

class InventoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Get pagination and filter parameters
        $perPage = $request->input('per_page', 50);
        $page = $request->input('page', 1);
        $q = $request->input('q', '');
        $status = $request->input('status', '');

        // Build inventory query with filters
        $inventoryQuery = Inventory::query()
            ->when($q, fn($query) => $query->where('name', 'like', "%$q%"))
            ->orderByDesc('created_at');

        // Total count for pagination
        $total = $inventoryQuery->count();

        // Paginate inventory items
        $inventories = $inventoryQuery->paginate($perPage, ['*'], 'page', $page);

   

        // Return JSON response
        return response()->json([
           'data' => $inventories->items(),
            'pagination' => [
                'current_page' => $inventories->currentPage(),
                'last_page' => $inventories->lastPage(),
                'per_page' => $inventories->perPage(),
                'total' => $inventories->total(),
                'next_page' => $inventories->nextPageUrl(),
                'prev_page' => $inventories->previousPageUrl(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'quantity_type' => 'required|string|max:255',
            'quantity' => 'required|numeric',
            'price' => 'required|numeric',
            'description' => 'nullable|string',
            'branch_id' => 'required|exists:branches,id',
        ]);
        try {


            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('images'), $imageName);
                $data['image'] = $imageName;
            }

            $inventory = Inventory::create($data);
            return response()->json([
                'status' => 'success',
                'message' => 'Inventory created successfully',
                'data' => $inventory
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create inventory: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $inventory = Inventory::findOrFail($id);
            return response()->json([
                'status' => 'success',
                'data' => $inventory
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Inventory not found'
            ], 404);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        try {
            $inventory = Inventory::findOrFail($id);
            return response()->json([
                'status' => 'success',
                'data' => $inventory
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Inventory not found'
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $inventory = Inventory::findOrFail($id);
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'quantity_type' => 'required|string|max:255',
                'quantity' => 'required|numeric',
                'price' => 'required|numeric',
                'description' => 'nullable|string',
                'branch_id' => 'required|exists:branches,id',
            ]);

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('images'), $imageName);
                $data['image'] = $imageName;
            }

            $inventory->update($data);
            return response()->json([
                'status' => 'success',
                'message' => 'Inventory updated successfully',
                'data' => $inventory
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update inventory: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $inventory = Inventory::findOrFail($id);
            $inventory->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Inventory deleted successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete inventory: ' . $e->getMessage()
            ], 500);
        }
    }
}
