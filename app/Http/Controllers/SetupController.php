<?php

namespace App\Http\Controllers;

use App\Models\User;
use Faker\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SetupController extends Controller {

    public static function needsSetup() {
        return env("ADMIN_PASSWORD", "") == "";
    }


    public function view() {
        $faker = Factory::create();
        $password = $faker->password(16, 16);
        $password = str_replace('"', '', $password);    // it's just easier for the .env file

        return view('setup', [
            "password" => $password,
        ]);
    }

    public function setup() {

        $path = base_path('.env');

        if (file_exists($path)) {
            file_put_contents($path, str_replace(
                'ADMIN_PASSWORD=' . env("ADMIN_PASSWORD"),
                'ADMIN_PASSWORD=' . request("password"),
                file_get_contents($path)
            ));
        }

        $result = redirect("/");

        return $result;
    }
}
