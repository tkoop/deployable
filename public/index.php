<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

define('LARAVEL_START', microtime(true));

/*
 * Deployable setup
 */

if (!file_exists('../database/database.sqlite')) {
	copy('../database/empty.database.sqlite', '../database/database.sqlite');
}

function runThis($command) {
	$output = [];
	exec($command, $output);
	echo "<div style='margin-top:12px'>$command</div>";
	flush();
	echo "<div>" . implode("<br>", $output) . "</div>";
	flush();
}

if (!file_exists('../.env')) {
	chdir("..");
	runThis('pwd');
	runThis('cp .env.example .env');
	runThis('php artisan cache:clear');
	runThis('php artisan key:generate');
	runThis('php artisan cache:clear');
	runThis('composer install');
	runThis('npm ci');
	runThis('php artisan cache:clear');
	
	$env = file_get_contents('.env');
	$env = str_replace('APP_URL=', 'APP_URL=http://' . $_SERVER["HTTP_HOST"], $env);
	file_put_contents('.env', $env);
	
	runThis('php artisan cache:clear');

	echo "<p>Refresh the page to continue.</p>";

	return;
}


/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is in maintenance / demo mode via the "down" command
| we will load this file so that any pre-rendered content can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists($maintenance = __DIR__ . '/../storage/framework/maintenance.php')) {
	require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

require __DIR__ . '/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
	$request = Request::capture()
)->send();

$kernel->terminate($request, $response);
