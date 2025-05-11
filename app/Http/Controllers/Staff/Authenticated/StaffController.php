<?php

namespace App\Http\Controllers\Staff\Authenticated;

use App\Http\Controllers\Controller;
use App\Models\CrmUser;
use App\Models\Staff;
use App\Models\AssignedJobs;
use App\Models\StaffAttendence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        // Get pagination and filter parameters
        $perPage = $request->input('per_page', 50);
        $page = $request->input('page', 1);
        $q = $request->input('q', '');
        $status = $request->input('status', '');
        $role = $request->input('role', '');
        $branch_id = $request->input('branch_id', '');

        // Build users query with filters
        $usersQuery = Staff::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('full_name', 'like', "%$q%")
                        ->orWhere('father_name', 'like', "%$q%")
                        ->orWhere('contact_email', 'like', "%$q%")
                        ->orWhere('phone_number', 'like', "%$q%")
                        ->orWhere('secondary_phone_number', 'like', "%$q%")
                        ->orWhere('unique_id', 'like', "%$q%");
                });
            })
            ->when($status, fn($query) => $query->where('status', $status))
            ->when($role, fn($query) => $query->where('role', $role))
            ->when($branch_id, fn($query) => $query->where('branch_id', $branch_id))
            ->orderByDesc('created_at');

        // Total count for pagination
        $total = $usersQuery->count();

        // Get paginated users
        $users = $usersQuery->paginate($perPage, ['*'], 'page', $page);

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
            'data' => $users->items(),
            'pagination' => $paginationData,
        ]);
    }


    public function store(Request $request)
    {
        $request->validate([
            'full_name' => 'required|string|max:255|unique:staff,full_name',
            'father_name' => 'required|string|max:255',
            'contact_email' => 'nullable|email|unique:staff,contact_email',
            'phone_number' => 'required|string|unique:staff,phone_number|unique:staff,phone_number',
            'secondary_phone_number' => 'nullable|string|unique:staff,secondary_phone_number|unique:staff,secondary_phone_number',
            'password' => 'required|string|min:8',
            'full_address' => 'required|string|max:255',
            'state' => 'required',
            'city' => 'required',
            'salary' => 'required|numeric',
            'branch_id' => 'required|exists:branches,id',
            'cnic_front' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'cnic_back' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'account_maintanance_certificate' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'blank_check' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'reference_1_name' => 'nullable|string|max:255',
            'reference_1_number' => 'nullable|string|max:255',
            'reference_1_cnic' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'reference_2_name' => 'nullable|string|max:255',
            'reference_2_number' => 'nullable|string|max:255',
            'reference_2_cnic' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'notification' => 'required|string',
            'role' => 'required|string|max:255',
            'status' => 'required|string|max:255',
            'has_crm_access' => 'required|string|max:255|in:yes,no',
        ]);
        try {
            $request->merge(['password' => Hash::make($request->password)]);
            $request->merge(['username' => Str::slug($request->full_name)]);
            // Create worker first to get ID

            $staff = Staff::create($request->except([
                'cnic_front',
                'cnic_back',
                'account_maintanance_certificate',
                'blank_check',
                'reference_1_cnic',
                'reference_2_cnic',
                'profile_image'
            ]));
            if ($staff && $request->has_crm_access == 'yes') {
                $crmUser = CrmUser::create([
                    'username' => $request->username,
                    'email' => $request->contact_email,
                    'phone' => $request->phone_number,
                    'password' => $request->password,
                    'role' => 'staff',
                    'status' => 'active',
                ]);
            }
            // Upload files to worker-specific folder
            if ($request->hasFile('cnic_front')) {
                $staff->cnic_front = $request->file('cnic_front')
                    ->store('public/staff/' . $staff->id . '/documents');
            }
            if ($request->hasFile('cnic_back')) {
                $staff->cnic_back = $request->file('cnic_back')
                    ->store('public/staff/' . $staff->id . '/documents');
            }
            if ($request->hasFile('account_maintanance_certificate')) {
                $staff->account_maintanance_certificate = $request->file('account_maintanance_certificate')
                    ->store('public/staff/' . $staff->id . '/documents');
            }
            if ($request->hasFile('blank_check')) {
                $staff->blank_check = $request->file('blank_check')
                    ->store('public/staff/' . $staff->id . '/documents');
            }
            if ($request->hasFile('reference_1_cnic')) {
                $staff->reference_1_cnic = $request->file('reference_1_cnic')
                    ->store('public/staff/' . $staff->id . '/documents');
            }
            if ($request->hasFile('reference_2_cnic')) {
                $staff->reference_2_cnic = $request->file('reference_2_cnic')
                    ->store('public/staff/' . $staff->id . '/documents');
            }
            if ($request->hasFile('profile_image')) {
                $staff->profile_image = $request->file('profile_image')
                    ->store('public/staff/' . $staff->id . '/profile');
            }

            $staff->save();

            return response()->json([
                "status" => "success",
                "message" => "Staff has been created",
            ], 200);
        } catch (\Exception $err) {
            // Log the error and return a response
            Log::error("Error creating Staff: " . $err->getMessage(), [
                'stack' => $err->getTraceAsString(),
            ]);
            return response()->json([
                "status" => "error",
                "message" => "Error creating Staff: " . $err->getMessage(),
            ], 500);
        }
    }
    public function show($id)
    {
        $staff = Staff::find($id);
        return response()->json($staff);
    }

    public function update(Request $request, $id)
{
    $staff = Staff::findOrFail($id);

    // Validate request data
    $validatedData = $request->validate([
        'full_name' => 'sometimes|string|max:255|unique:staff,full_name,' . $id,
        'father_name' => 'sometimes|string|max:255',
        'contact_email' => 'sometimes|nullable|email|unique:staff,contact_email,' . $id,
        'phone_number' => 'sometimes|string|unique:staff,phone_number,' . $id,
        'secondary_phone_number' => 'sometimes|nullable|string|unique:staff,secondary_phone_number,' . $id,
        'password' => 'sometimes|nullable|string|min:8',
        'full_address' => 'sometimes|string|max:255',
        'state' => 'sometimes',
        'city' => 'sometimes',
        'salary' => 'sometimes|numeric',
        'branch_id' => 'sometimes|exists:branches,id',
        'notification' => 'sometimes|string',
        'role' => 'sometimes|string|max:255',
        'status' => 'sometimes|string|max:255',
        'has_crm_access' => 'sometimes|string|max:255|in:yes,no',
    ]);

    // Update password only if provided
    if ($request->filled('password')) {
        $validatedData['password'] = Hash::make($request->password);
    } else {
        unset($validatedData['password']);
    }

    // Update staff record
    $staff->update($validatedData);

    return response()->json([
        "status" => "success",
        "message" => "Staff has been updated",
        "staff" => $staff
    ], 200);
}


    public function destroy($id)
    {
        try {
            $staff = Staff::find($id);

            if (!$staff) {
                return response()->json([
                    "status" => "error",
                    "message" => "Staff member not found"
                ], 404);
            }

            // Check for assigned jobs
            $jobs = AssignedJobs::where('assigned_to', $staff->id)->get();
            if ($jobs->count() > 0) {
                $deleteJobs = $jobs->delete();
                if (!$deleteJobs) {
                    return response()->json([
                        "status" => "error",
                        "message" => "Cannot delete staff member with assigned jobs",
                    ], 400);
                }
            }


            // Check for attendance records
            $attendanceRecords = StaffAttendence::where('staff_id', $staff->id)->exists();
            if ($attendanceRecords) {
                $deleteAttendance = StaffAttendence::where('staff_id', $staff->id)->delete();
                if (!$deleteAttendance) {
                    return response()->json([
                        "status" => "error",
                        "message" => "Cannot delete staff member with attendance records",
                    ], 400);
                }
            }


            $staff->delete();

            return response()->json([
                "status" => "success",
                "message" => "Staff has been deleted",
            ], 200);
        } catch (\Exception $err) {
            Log::error("Error deleting Staff: " . $err->getMessage(), [
                'stack' => $err->getTraceAsString(),
            ]);
            return response()->json([
                "status" => "error",
                "message" => "Error deleting Staff: " . $err->getMessage(),
            ], 500);
        }
    }
    public function updateProfileImage(Request $request)
    {
        $user = $request->user();
        $staff = Staff::find($user->id);
        $fileName = time() . '_' . $request->file('profile_image')->getClientOriginalName();
        $path = $request->file('profile_image')->storeAs('staff/' . $staff->id . '/profile', $fileName, 'public');
        $staff->profile_image = $path;
        $staff->save();
        return response()->json([
            "status" => "success",
            "message" => "Profile image has been updated",
        ], 200);
    }
}
