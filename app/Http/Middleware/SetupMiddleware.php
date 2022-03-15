<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SetupMiddleware {
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next) {
        if (!file_exists('../.env')) {
            copy('../.env.example', '../.env');
            Artisan::call('cache:clear');
            Artisan::call('key:generate');
        }

        if (filesize('../database/database.sqlite') == 0) {
            Artisan::call('migrate');
        }

        return $next($request);
    }
}
