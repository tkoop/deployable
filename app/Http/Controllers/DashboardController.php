<?php

namespace App\Http\Controllers;

use App\Models\Hook;

class DashboardController extends Controller {

    public function view() {
        $hooks = Hook::orderBy("name")->get();

        return view('dashboard', ["hooks" => $hooks]);
    }
}
