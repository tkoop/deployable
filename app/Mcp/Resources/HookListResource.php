<?php

namespace App\Mcp\Resources;

use App\Mcp\Support\Hooks;
use App\Models\Hook;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Description('Every deploy hook as JSON: name, slug, webhook URL, project directory, script hash, and last deploy time. Read this to find a hook id without spending a tool call.')]
#[MimeType('application/json')]
#[Uri('deployable://hooks')]
class HookListResource extends Resource {
	public function handle(Request $request): Response {
		$hooks = Hook::orderBy("name")->get();

		return Response::json([
			"hooks" => $hooks->map(fn ($hook) => Hooks::summary($hook))->all(),
		]);
	}
}
