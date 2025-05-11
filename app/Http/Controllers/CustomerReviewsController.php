<?php

namespace App\Http\Controllers;

use App\Models\CustomerReviews;
use Exception;
use Illuminate\Http\Request;

class CustomerReviewsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function fetch($complaintId)
    {
        $customerReviews = CustomerReviews::where('complaint_id', $complaintId)->with('user')->get();
        return response()->json($customerReviews);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $payload = $request->validate([
            'complaint_id' => 'required|exists:complaints,id',
            'rating' => 'required|integer|min:1|max:10',
            'reason' => 'required_if:rating,1,2,3,4,5,6,7,8|string|nullable',
            'comment' => 'nullable|string',
        ]);
        $user = $request->user();
        $payload['user_id'] = $user->id;
        try {
            $customerReview = CustomerReviews::create($payload);
            return response()->json([
                "status" => "success",
                "message" => "Customer review has been created successfully",
            ]);
        } catch (Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($complaintId)
    {
        $customerReviews = CustomerReviews::where('complaint_id', $complaintId)->with('user')->get();
        return response()->json($customerReviews);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $reviewId)
    {
        $customerReviews = CustomerReviews::where('id', $reviewId)->first();
        $payload = $request->validate([
            'rating' => 'required|integer|min:1|max:10',
            'reason' => 'required_if:rating,1,2,3,4,5,6,7,8|string|nullable',
            'comment' => 'nullable|string',
        ]);

        try {
            $customerReviews->update($payload);
            return response()->json([
                "status" => "success",
                "message" => "Customer review has been updated successfully"
            ]);
        } catch (Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($reviewId)
    {
        $customerReviews = CustomerReviews::where('id', $reviewId)->first();
        try {
            $customerReviews->delete();
            return response()->json([
                "status" => "success",
                "message" => "Customer review has been deleted successfully"
            ]);
        } catch (Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage()
            ], 500);
        }
    }
}
