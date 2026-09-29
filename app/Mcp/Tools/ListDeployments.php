<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Hooks;
use App\Models\Deployment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List deployment history, newest first, optionally filtered to one hook or one state.')]
#[IsReadOnly]
class ListDeployments extends BaseTool {
	protected function run(Request $request): Response|ResponseFactory {
		$hookId = $request->get("hook_id");
		$slug = $request->get("slug");

		$query = Deployment::query()->with("hook");

		if ($hookId !== null || $slug !== null) {
			$hook = Hooks::resolve($hookId !== null ? (int) $hookId : null, $slug);
			$query->where("hook_id", $hook->id);
		}

		$state = $request->get("state");
		if ($state != null) {
			$query->where("state", $state);
		}

		$limit = min(max((int) ($request->get("limit") ?? 20), 1), 100);

		$deployments = $query->orderBy("id", "desc")->limit($limit)->get();

		return Response::structured([
			"deployments" => $deployments->map(fn ($deployment) => Hooks::deploymentSummary($deployment))->all(),
			"count" => $deployments->count(),
		]);
	}

	public function schema(JsonSchema $schema): array {
		return [
			"hook_id" => $schema->integer()
				->description('Only deployments of this hook.'),
			"slug" => $schema->string()
				->description('Only deployments of this hook, by slug. Fails if the slug is ambiguous.'),
			"state" => $schema->string()
				->enum(["started", "running", "done", "failed"])
				->description(
					'Filter by state. Note this app never actually writes "failed" — no exit code is ' .
					'captured — so it will always come back empty.'
				),
			"limit" => $schema->integer()
				->description('How many to return, newest first. Defaults to 20, capped at 100.')
				->default(20),
		];
	}

	public function outputSchema(JsonSchema $schema): array {
		return [
			"deployments" => $schema->array()->required(),
			"count" => $schema->integer()->required(),
		];
	}
}
