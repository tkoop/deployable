<?php

namespace App\Http\Controllers;

use App\Models\User;
use Faker\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SetupController extends Controller {


    public function view() {
        $copyEnvFile = !file_exists('../.env');
        $addPassword = env("ADMIN_PASSWORD") == null;
        $setupDB = !file_exists("../database/database.sqlite");

        $faker = Factory::create();
        $password = $faker->password(16, 16);
        $password = str_replace('"', '', $password);    // it's just easier for the .env file

        return view('setup', [
            "copyEnvFile" => $copyEnvFile,
            "addPassword" => $addPassword,
            "setupDB" => $setupDB,
            "password" => $password,
        ]);
    }

    public function setup() {
        return redirect("/setup")->withStatus("nyi");
    }
}
