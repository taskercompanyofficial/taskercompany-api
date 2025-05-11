<?php

namespace App\Http\Controllers;

use App\Models\CsoRemarks;
use Exception;
use Illuminate\Http\Request;

class CsoRemarksController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($complaintId)
    {
        $csoRemarks = CsoRemarks::where('complaint_id', $complaintId)->with('user')->get();
        return response()->json($csoRemarks);
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
            'remarks' => 'required|string'
        ]);

        $user = $request->user();
        $payload['user_id'] = $user->id;

        try {
            $csoRemark = CsoRemarks::create($payload);
            return response()->json([
                "status" => "success",
                "message" => "CSO remark has been created successfully"
            ]);
        } catch (Exception $e) {
            return response()->json([
                "status" => "error", 
                "message" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($complaintId)
    {
        $csoRemarks = CsoRemarks::where('complaint_id', $complaintId)->with('user')->get();
        return response()->json($csoRemarks);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CsoRemarks $csoRemarks)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $remarkId)
    {
        $csoRemark = CsoRemarks::where('id', $remarkId)->first();
        
        $payload = $request->validate([
            'remarks' => 'required|string'
        ]);

        try {
            $csoRemark->update($payload);
            return response()->json([
                "status" => "success",
                "message" => "CSO remark has been updated successfully"
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
    public function destroy($remarkId)
    {
        $csoRemark = CsoRemarks::where('id', $remarkId)->first();
        try {
            $csoRemark->delete();
            return response()->json([
                "status" => "success",
                "message" => "CSO remark has been deleted successfully"
            ]);
        } catch (Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage()
            ], 500);
        }
    }
}
