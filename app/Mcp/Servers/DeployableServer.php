<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\DeploymentOutputResource;
use App\Mcp\Resources\HookListResource;
use App\Mcp\Resources\HookResource;
use App\Mcp\Tools\AppendToHookScript;
use App\Mcp\Tools\CreateHook;
use App\Mcp\Tools\DeleteHook;
use App\Mcp\Tools\GetDeployment;
use App\Mcp\Tools\GetDeploymentOutput;
use App\Mcp\Tools\GetHook;
use App\Mcp\Tools\ListDeployments;
use App\Mcp\Tools\ListHooks;
use App\Mcp\Tools\TriggerDeploy;
use App\Mcp\Tools\UpdateHook;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Deployable')]
#[Version('1.0.0')]
#[Instructions(<<<'TEXT'
Deployable holds deploy hooks: a named bash script plus a slug that triggers it from a
webhook. A trigger creates a deployment, runs the script in the background, and records
output to disk.

Things that will bite you:

- Scripts have no reliable working directory. The generated runner does a relative `cd ..`
  from whatever directory the web server happened to start in, so always use absolute paths
  in a script.
- A deployment's `succeeded` field tells you whether the script exited 0. It is null
  while the deploy is still running, and also null for old deployments recorded before
  exit codes were captured — so treat null as unknown rather than as success.
- A deployment stuck in "started" or "running" may be mid-run or may have been killed
  without running its EXIT trap. The recorded pid is the artisan process, not the script,
  so liveness can't be checked. Poll get_deployment rather than assuming.
- Slugs are not unique in the database. Any tool that takes a slug fails loudly if more
  than one hook matches. Prefer hook_id.
- Writing a script needs the script_hash from a recent get_hook. If it doesn't match, the
  script changed in the browser and you must re-read before retrying.
- Deleting a hook cascades: its deployment history is destroyed too.
TEXT)]
class DeployableServer extends Server {
	/**
	 * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
	 */
	protected array $tools = [
		ListHooks::class,
		GetHook::class,
		ListDeployments::class,
		GetDeployment::class,
		GetDeploymentOutput::class,
		TriggerDeploy::class,
		CreateHook::class,
		UpdateHook::class,
		AppendToHookScript::class,
		DeleteHook::class,
	];

	/**
	 * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
	 */
	protected array $resources = [
		HookListResource::class,
		HookResource::class,
		DeploymentOutputResource::class,
	];
}
