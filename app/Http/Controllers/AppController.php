<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AppController extends Controller {

    public function viewNew() {
        $path = app_path();
        $path = substr($path, 0, strrpos($path, '/')) . '/newapp';

        return view('newApp', ["path" => $path]);
    }

    public function doNew() {
        return redirect('/')->withError('nyi');
    }
}
