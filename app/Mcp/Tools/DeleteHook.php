<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Hooks;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Delete a hook and everything attached to it. The deployments table has a cascading foreign key, so this permanently destroys that hook\'s entire deployment history — there is no undo.')]
#[IsDestructive(true)]
class DeleteHook extends BaseTool {
	protected function run(Request $request): Response|ResponseFactory {
		$hook = Hooks::resolve(
			$request->get("hook_id") !== null ? (int) $request->get("hook_id") : null,
			$request->get("slug")
		);

		$summary = Hooks::summary($hook);
		$deploymentsDeleted = $hook->deployments()->count();

		$hook->delete();

		return Response::structured([
			"deleted" => true,
			"hook" => $summary,
			"deployments_deleted" => $deploymentsDeleted,
		]);
	}

	public function schema(JsonSchema $schema): array {
		return [
			"hook_id" => $schema->integer()
				->description('The hook to delete. Preferred, since slugs are not unique in this app.')
				->required(),
		];
	}

	public function outputSchema(JsonSchema $schema): array {
		return [
			"deleted" => $schema->boolean()->required(),
			"hook" => $schema->object([
				"id" => $schema->integer()->required(),
				"name" => $schema->string()->required(),
				"slug" => $schema->string()->required(),
				"deployment_count" => $schema->integer()->required(),
			])->required()
				->description('The hook as it looked immediately before deletion.'),
			"deployments_deleted" => $schema->integer()->required()
				->description('Deployment rows destroyed by the cascading foreign key.'),
		];
	}
}
