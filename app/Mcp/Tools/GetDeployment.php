<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Deployments;
use App\Mcp\Support\Hooks;
use App\Models\Deployment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description("Get a single deployment's state and timings without pulling its output. Cheap way to poll whether a deploy you started has finished.")]
#[IsReadOnly]
class GetDeployment extends BaseTool {
	protected function run(Request $request): Response|ResponseFactory {
		$deployment = Deployment::with("hook")->find((int) $request->get("deployment_id"));

		if ($deployment == null) {
			return Response::error("There's no deployment with id {$request->get('deployment_id')}.");
		}

		$path = Deployments::outputPath($deployment);

		return Response::structured([
			"deployment" => Hooks::deploymentSummary($deployment) + [
				"still_running" => $deployment->isRunning(),
				"output_exists" => is_file($path),
				"output_path" => $path,
			],
		]);
	}

	public function schema(JsonSchema $schema): array {
		return [
			"deployment_id" => $schema->integer()
				->description('The deployment id, as returned by trigger_deploy or list_deployments.')
				->required(),
		];
	}

	public function outputSchema(JsonSchema $schema): array {
		return [
			"deployment" => $schema->object([
				"id" => $schema->integer()->required(),
				"hook_id" => $schema->integer()->required(),
				"hook_name" => $schema->string()->nullable()->required(),
				"state" => $schema->string()->required()
					->description('started, running, done, or failed.'),
				"succeeded" => $schema->boolean()->nullable()->required()
					->description('True if the script exited 0, false if it exited non-zero, null while running or when no exit status was recorded.'),
				"exit_code" => $schema->integer()->nullable()->required()
					->description('How the deploy script exited. Null while running.'),
				"created_at" => $schema->string()->nullable()->required(),
				"ended_at" => $schema->string()->nullable()->required(),
				"duration_seconds" => $schema->integer()->nullable()->required(),
				"still_running" => $schema->boolean()->required(),
				"output_exists" => $schema->boolean()->required(),
				"output_path" => $schema->string()->required(),
			])->required(),
		];
	}
}
