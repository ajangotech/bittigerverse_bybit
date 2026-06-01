<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function viewLogin()
    {
        return view('login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return response()->json(['redirect' => '/dashboard']);
        }

        return response()->json(['errors' => ['email' => ['Invalid credentials.']]], 422);
    }

    public function viewRegister()
    {
        return view('register');
    }

    public function register(Request $request)
    {
        return response()->json([
            'message'  => 'Something went wrong',
            'redirect' => route('login.form')
        ], 500);
    }

    /*
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name'        => 'required|string|max:255',
            'last_name'         => 'required|string|max:255',
            'email'             => 'required|email|unique:users,email',
            'password'          => 'required|min:6|confirmed',
            'bybit_api_key'     => 'required|string',
            'bybit_api_secret'  => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'first_name'       => $request->first_name,
            'last_name'        => $request->last_name,
            'email'            => $request->email,
            'password'         => Hash::make($request->password),
            'bybit_api_key'    => $request->bybit_api_key,
            'bybit_api_secret' => $request->bybit_api_secret,
            'status'           => 'active',
            'role'             => 'user',
        ]);

        return response()->json([
            'message'  => 'Registration successful',
            'redirect' => route('login.form')
        ], 201);
    }
    */
}
