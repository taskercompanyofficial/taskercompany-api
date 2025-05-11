<?php

namespace App\Http\Controllers\Application\Auth;

use App\Http\Controllers\Controller;
use App\Models\AppUsers;
use App\Models\VerificationOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|unique:app_users',
            'password' => 'required|string|min:8',
        ]);

        $user = AppUsers::create([
            'name' => $request->name,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
        ]);
        if ($user) {
            try {
                $otp = rand(100000, 999999);
                VerificationOtp::create([
                    'user_id' => $user->id,
                    'otp' => $otp,
                    'expires_at' => now()->addMinutes(10),
                ]);
                return response()->json([
                    "status" => "success",
                    "message" => "Account created successfully!",
                    "user" => $user,
                ], 200);
            } catch (\Exception $e) {
                // Delete the user if OTP creation fails
                $user->delete();
                return response()->json([
                    'status' => "error",
                    'message' => 'Failed to create verification OTP',
                ], 500);
            }
        }
        return response()->json([
            'status' => "error",
            'message' => 'User created failed',
        ]);
    }
}
