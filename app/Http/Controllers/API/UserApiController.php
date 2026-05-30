<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserApiController extends Controller
{
    private function checkAdmin()
    {
        if (auth('api')->user()->role !== 'admin') {
            abort(403, 'Unauthorized');
        }
    }

    public function index()
    {
        $this->checkAdmin();

        return response()->json(
            User::where('role', '!=', 'admin')->latest()->get()
        );
    }

    public function store(Request $request)
    {
        $this->checkAdmin();

        $request->validate([
            'first_name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
        ]);

        $user = User::create([
            ...$request->only(['first_name','last_name','email']),
            'password' => Hash::make($request->password),
            'role' => 'user'
        ]);

        return response()->json(['user' => $user]);
    }

    public function destroy($id)
    {
        $this->checkAdmin();

        User::findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}