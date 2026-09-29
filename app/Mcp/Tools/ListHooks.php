<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Hooks;
use App\Models\Hook;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List every deploy hook with its slug, project directory, and last deploy time. Start here to find a hook_id.')]
#[IsReadOnly]
class ListHooks extends BaseTool {
	protected function run(Request $request): Response|ResponseFactory {
		$search = $request->get("search");

		$hooks = Hook::query()
			->when($search, function ($query) use ($search) {
				$query->where(function ($query) use ($search) {
					$query->where("name", "like", "%{$search}%")
						->orWhere("slug", "like", "%{$search}%");
				});
			})
			->orderBy("name")
			->get();

		return Response::structured([
			"hooks" => $hooks->map(fn ($hook) => Hooks::summary($hook))->all(),
			"count" => $hooks->count(),
		]);
	}

	public function schema(JsonSchema $schema): array {
		return [
			"search" => $schema->string()
				->description('Optional filter, matched against the hook name and slug.'),
		];
	}

	public function outputSchema(JsonSchema $schema): array {
		return [
			"hooks" => $schema->array()->description("The matching hooks, sorted by name.")
				->required(),
			"count" => $schema->integer()->description("How many hooks matched.")
				->required(),
		];
	}
}
