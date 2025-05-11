<?php

namespace App\Http\Controllers;

use App\Models\Expences;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ExpencesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');
        $category = $request->input('category');
        $sort = $request->input('sort', 'desc');

        $expenses = Expences::where('user_id', $user->id);

        if ($startDate) {
            $startDate = \Carbon\Carbon::parse($startDate)->startOfDay();
            $expenses->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $endDate = \Carbon\Carbon::parse($endDate)->endOfDay();
            $expenses->whereDate('created_at', '<=', $endDate);
        }

        if ($category && $category !== 'all') {
            $expenses->where('category', $category);
        }

        if ($sort === 'desc') {
            $expenses->orderBy('created_at', 'desc');
        } else {
            $expenses->orderBy('created_at', 'asc');
        }

        $expenses = $expenses->get();
        return response()->json([
            'data' => $expenses
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric',
            'description' => 'required|string',
            'category' => 'required|string',
            'document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048'
        ]);

        $expense = new Expences();
        $expense->amount = $request->amount;
        $expense->description = $request->description;
        $expense->category = $request->category;
        $expense->user_id = $request->user()->id;

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $path = $file->store('expenses', 'public');
            $expense->document = $path;
        }

        $expense->save();

        return response()->json([
            'message' => 'Expense created successfully',
            'expense' => $expense
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id)
    {
        if (!$id) {
            return response()->json(['message' => 'Expense not found'], 404);
        }
        $expense = Expences::find($id);
        return response()->json([
            'data' => $expense
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        if (!$id) {
            return response()->json(['message' => 'Expense not found'], 404);
        }
        $request->validate([
            'amount' => 'required|numeric',
            'description' => 'required|string',
            'category' => 'required|string',
            'document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048'
        ]);

        $expense = Expences::find($id);
        $expense->amount = $request->amount;
        $expense->description = $request->description;
        $expense->category = $request->category;

        if ($request->hasFile('document')) {
            // Delete old document if exists
            if ($expense->document) {
                Storage::disk('public')->delete($expense->document);
            }

            $file = $request->file('document');
            $path = $file->store('expenses', 'public');
            $expense->document = $path;
        }

        $expense->save();

        return response()->json([
            'message' => 'Expense updated successfully',
            'expense' => $expense
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if (!$id) {
            return response()->json(['message' => 'Expense not found'], 404);
        }
        $expense = Expences::find($id);


        if ($expense->document) {
            Storage::disk('public')->delete($expense->document);
        }

        $expense->delete();

        return response()->json([
            'message' => 'Expense deleted successfully'
        ]);
    }
    public function getExpenses()
{
    // Get the authenticated user
    $user = auth()->user();

    // Check if the user is an administrator
    if ($user->role === 'administrator') {
        // Administrator: Fetch all expenses
        $totalExpense = Expences::sum('amount');

        $monthlyExpense = Expences::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        $todayExpense = Expences::whereDate('created_at', today())
            ->sum('amount');

        $yesterdayExpense = Expences::whereDate('created_at', today()->subDay())
            ->sum('amount');
    } else {
        // Regular user: Fetch expenses associated with the user
        $totalExpense = Expences::where('user_id', $user->id)
            ->sum('amount');

        $monthlyExpense = Expences::where('user_id', $user->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        $todayExpense = Expences::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->sum('amount');

        $yesterdayExpense = Expences::where('user_id', $user->id)
            ->whereDate('created_at', today()->subDay())
            ->sum('amount');
    }

    return response()->json([
        'data' => [
            'totalExpense' => $totalExpense,
            'monthlyExpense' => $monthlyExpense,
            'todayExpense' => $todayExpense,
            'yesterdayExpense' => $yesterdayExpense
        ]
    ]);
}
}
