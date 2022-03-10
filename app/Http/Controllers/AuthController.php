<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller {
    

    public function view() {
        return view('auth.login');
    }

    public function login() {
        
        return view('auth.login');
    }

    public function logout() {
        return view('auth.login');
    }
}
