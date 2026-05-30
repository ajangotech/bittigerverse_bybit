<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Ads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DashboardApiController extends Controller
{
    public function stats()
    {
        return response()->json([
            'users'  => User::count(),
            'ads'    => Ads::count(),
            'orders' => 0 // placeholder (until you add orders table)
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password'     => 'required|min:6|confirmed',
        ]);

        $user = auth('api')->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Wrong password'], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json(['message' => 'Password updated']);
    }

    public function orders(Request $request)
    {
        return response()->json([
            'data' => [],
            'status' => $request->status ?? 'all'
        ]);
    }

    public function payments()
    {
        return response()->json([
            'data' => []
        ]);
    }
}