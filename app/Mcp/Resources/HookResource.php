<?php

namespace App\Mcp\Resources;

use App\Mcp\Exceptions\ToolException;
use App\Mcp\Support\Hooks;
use App\Models\Hook;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Description('One deploy hook in full, including its bash script and the script_hash you pass back to update_hook or append_to_hook_script.')]
#[MimeType('application/json')]
class HookResource extends Resource implements HasUriTemplate {
	public function uriTemplate(): UriTemplate {
		return new UriTemplate('deployable://hooks/{hookId}');
	}

	public function handle(Request $request): Response {
		$id = $request->get("hookId");

		if (!ctype_digit((string) $id)) {
			throw new ToolException("'{$id}' isn't a hook id.");
		}

		$hook = Hook::find((int) $id);

		if ($hook == null) {
			throw new ToolException("There's no hook with id {$id}.");
		}

		return Response::json(["hook" => Hooks::detail($hook)]);
	}
}
