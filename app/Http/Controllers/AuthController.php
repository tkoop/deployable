<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller {


    public function view() {
        return view('auth.login');
    }

    public function login() {
        Log::debug("login");
        if (request("password") == env("ADMIN_PASSWORD")) {
            if (User::count() == 0) {
                User::create([
                    "name" => "Admin"
                ]);
            }
            Auth::login(User::first());
            return redirect()->route("dashboard")->with('status', "You're logged in.");
        } else {
            return back()->withErrors("Wrong password.");
        }
    }

    public function logout() {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect()->route('login')->with('status', "You're logged out.");
    }
}
