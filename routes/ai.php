<?php

use App\Mcp\Servers\DeployableServer;
use Laravel\Mcp\Facades\Mcp;

// Registered by Laravel\Mcp's own service provider, not by RouteServiceProvider,
// so it survives the setup-mode early return in routes/web.php.
Mcp::web('/mcp', DeployableServer::class)
    ->middleware('auth:sanctum');
