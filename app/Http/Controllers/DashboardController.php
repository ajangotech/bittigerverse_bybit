<?php

namespace App\Http\Controllers;

use App\Models\Ads;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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

    public function logout(Request $request)
    {
        Auth::logout();
        return redirect('/')->with('status', 'You have been logged out.');
    }
}