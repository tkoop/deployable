<?php

namespace App\Http\Controllers;

use App\Models\Deployment;
use Illuminate\Http\Request;

class DeploymentController extends Controller {
    
    public function view(Deployment $deployment) {
        return view("deployment", ["deployment"=>$deployment]);
    }
}
