<?php

namespace App\Http\Controllers;

use App\Models\App;
use Illuminate\Http\Request;

class DashboardController extends Controller {

    public function view() {
        $apps = App::orderBy("name")->get();

        return view('dashboard', ["apps" => $apps]);
    }
}
