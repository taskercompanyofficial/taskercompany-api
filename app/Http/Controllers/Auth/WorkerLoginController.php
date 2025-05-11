<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StoreUserSpecific;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class WorkerLoginController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:staff,contact_email',
            'password' => 'required|string|min:8',
            "role" => "required|string"
        ]);

        $user = Staff::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            "role" => $request->role
        ]);

        return response()->json([
            'status' => "success",
            'message' => 'Register successful'
        ]);
    }

    public function checkCredentials(Request $request)
    {
        $request->validate([
            'email' => 'required|email|nullable',
            'password' => 'required|string'
        ]);

        try {
            $user = Staff::where('contact_email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Account not found'
                ], 404);
            }

            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid credentials'
                ], 401);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Login successful',
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage()
            ], 400);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email|nullable',
            'password' => 'required|string'
        ]);

        try {
            $user = Staff::where('contact_email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Account not found'
                ], 404);
            }

            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid credentials'
                ], 401);
            }

            $token = $user->createToken('web')->plainTextToken;
            $authRes = array_merge($user->toArray(), ['token' => $token]);

            return response()->json([
                'message' => 'Login successful',
                'user' => $authRes
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'status' => 'error',
                'message' => $err->getMessage()
            ], 400);
        }
    }

    public function logout(Request $request)
    {
        $userSpecificToken = StoreUserSpecific::where('user_id', $request->user()->id)->first();
        if ($userSpecificToken) {
            $userSpecificToken->delete();
        }
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'message' => 'Logout successful'
        ], 200);
    }
}
