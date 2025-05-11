<?php

namespace App\Http\Controllers\Application\Auth;

use App\Http\Controllers\Controller;
use App\Models\AppUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function checkCredentials(Request $request)
    {
        $request->validate([
            'phone' => 'required_without:email|string|nullable',
            'email' => 'required_without:phone|email|nullable',
            'password' => 'required|string'
        ]);

        try {

            $user = AppUsers::where('phone', $request->phone)->orWhere('email', $request->email)->first();

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
            'phone' => 'required|string|nullable',
            'password' => 'required|string'
        ]);

        try {
            $user = AppUsers::where('phone', $request->phone)->first();

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
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'message' => 'Logout successful'
        ], 200);
    }
}
