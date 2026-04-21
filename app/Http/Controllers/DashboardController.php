<?php

namespace App\Http\Controllers;

use App\Models\Ads;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $usersCount = User::count();
        $adsCount = Ads::count();

        $ads = Ads::where('user_id', $user->id)->latest()->get();

        return view('dashboard', compact(
            'user',
            'ads',
            'usersCount',
            'adsCount'
        ));
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Current password is incorrect'
            ]);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Password updated successfully'
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        return redirect('/')->with('status', 'You have been logged out.');
    }
}