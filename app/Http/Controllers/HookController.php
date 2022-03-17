<?php

namespace App\Http\Controllers;

use App\Models\Hook;
use Illuminate\Http\Request;

class HookController extends Controller {

    public function viewNew() {
        $slug = rand(0, 99999999) . rand(0, 99999999);
        $baseURL = url('hook');

        return view('newHook', ["slug" => $slug, "baseURL" => $baseURL]);
    }

    public function doNew() {
        request()->validate([
            "name" => "required",
            "slug" => "required",
        ]);

        Hook::create([
            "name" => request("name"),
            "slug" => request("slug"),
        ]);

        return redirect('/')->withError('nyi');
    }

    public function deploy(Hook $hook) {
        return redirect('/')->withStatus("Didn't actually deploy.");
    }

    public function viewEdit(Hook $hook) {
        return view('editHook', ["name" => $hook->name, "slug" => $hook->slug, "baseURL" => url('hook')]);
    }
}
