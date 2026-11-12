<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\DeployableServer;
use App\Mcp\Tools\SetHookEnvVariable;
use App\Models\Hook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnvToolsTest extends TestCase {
	use RefreshDatabase;

	protected function hook(array $attributes = []): Hook {
		$dir = sys_get_temp_dir() . "/deployable-test-" . uniqid();
		mkdir($dir);
		return Hook::create($attributes + [
			"name" => "Example",
			"slug" => "example",
			"script" => "echo hi",
			"directory" => $dir,
		]);
	}

	public function test_it_adds_a_variable_when_missing(): void {
		$hook = $this->hook();
		file_put_contents($hook->directory . "/.env", "EXISTING=1\n");

		DeployableServer::tool(SetHookEnvVariable::class, [
			"hook_id" => $hook->id,
			"name" => "NEW_VAR",
			"value" => "hello world",
		])->assertOk()
			->assertHasNoErrors()
			->assertStructuredContent(fn ($json) => $json
				->where("added", true)
				->where("reason", "added")
				->etc());

		$this->assertStringContainsString("NEW_VAR=\"hello world\"", file_get_contents($hook->directory . "/.env"));
		$this->assertStringContainsString("EXISTING=1", file_get_contents($hook->directory . "/.env"));
	}

	public function test_it_does_not_overwrite_an_existing_variable(): void {
		$hook = $this->hook();
		file_put_contents($hook->directory . "/.env", "NEW_VAR=original\n");

		DeployableServer::tool(SetHookEnvVariable::class, [
			"hook_id" => $hook->id,
			"name" => "NEW_VAR",
			"value" => "changed",
		])->assertOk()
			->assertHasNoErrors()
			->assertStructuredContent(fn ($json) => $json
				->where("added", false)
				->where("reason", "already_exists")
				->etc());

		$this->assertStringContainsString("NEW_VAR=original", file_get_contents($hook->directory . "/.env"));
		$this->assertStringNotContainsString("changed", file_get_contents($hook->directory . "/.env"));
	}

	public function test_it_requires_a_valid_variable_name(): void {
		$hook = $this->hook();

		DeployableServer::tool(SetHookEnvVariable::class, [
			"hook_id" => $hook->id,
			"name" => "1BAD",
			"value" => "x",
		])->assertHasErrors();
	}

	public function test_it_refuses_when_directory_missing(): void {
		$hook = Hook::create([
			"name" => "NoDir",
			"slug" => "nodir",
			"script" => "echo hi",
			"directory" => "/no/such/dir-" . uniqid(),
		]);

		DeployableServer::tool(SetHookEnvVariable::class, [
			"hook_id" => $hook->id,
			"name" => "VAR",
			"value" => "x",
		])->assertHasErrors()
			->assertSee("no project directory");
	}

	public function test_refresh_config_defaults_on(): void {
		$hook = $this->hook();

		DeployableServer::tool(SetHookEnvVariable::class, [
			"hook_id" => $hook->id,
			"name" => "VAR",
			"value" => "x",
		])->assertOk()
			->assertStructuredContent(fn ($json) => $json
				->where("config.success", false)
				->etc());
	}
}
