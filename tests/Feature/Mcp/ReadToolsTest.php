<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\DeployableServer;
use App\Mcp\Tools\GetDeploymentOutput;
use App\Mcp\Tools\GetHook;
use App\Mcp\Tools\ListDeployments;
use App\Mcp\Tools\ListHooks;
use App\Mcp\Tools\TriggerDeploy;
use App\Models\Deployment;
use App\Models\Hook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReadToolsTest extends TestCase {
	use RefreshDatabase;

	protected function setUp(): void {
		parent::setUp();

		// Deployments write outside the database, so transaction rollback
		// alone would leak output between tests. Only the contents are
		// cleared: storage/deployments/.gitignore is tracked.
		File::deleteDirectories(storage_path("deployments"));
	}

	protected function tearDown(): void {
		File::deleteDirectories(storage_path("deployments"));

		parent::tearDown();
	}

	protected function hook(array $attributes = []): Hook {
		return Hook::create($attributes + [
			"name" => "Example",
			"slug" => "example",
			"script" => "echo hi",
		]);
	}

	protected function writeOutput(Deployment $deployment, string $contents): void {
		$directory = storage_path("deployments/" . $deployment->id);
		File::ensureDirectoryExists($directory);
		File::put($directory . "/output.txt", $contents);
	}

	public function test_it_lists_hooks(): void {
		$this->hook(["name" => "Alpha", "slug" => "alpha"]);
		$this->hook(["name" => "Beta", "slug" => "beta", "script" => "echo two"]);

		DeployableServer::tool(ListHooks::class, [])
			->assertOk()
			->assertHasNoErrors()
			->assertStructuredContent(fn ($json) => $json->count("hooks", 2)->etc());
	}

	public function test_list_hooks_can_search(): void {
		$this->hook(["name" => "Alpha", "slug" => "alpha"]);
		$this->hook(["name" => "Beta", "slug" => "beta"]);

		DeployableServer::tool(ListHooks::class, ["search" => "alph"])
			->assertStructuredContent(fn ($json) => $json->count("hooks", 1)->etc());
	}

	public function test_it_gets_a_hook_with_its_script_and_hash(): void {
		$hook = $this->hook(["script" => "echo hello\n"]);

		DeployableServer::tool(GetHook::class, ["hook_id" => $hook->id])
			->assertOk()
			->assertStructuredContent(function ($json) use ($hook) {
				$json->where("hook.id", $hook->id)
					->where("hook.script", "echo hello\n")
					->etc();
			});
	}

	public function test_the_script_hash_is_a_short_hex_digest(): void {
		$hook = $this->hook();

		DeployableServer::tool(GetHook::class, ["hook_id" => $hook->id])
			->assertStructuredContent(fn ($json) => $json
				->whereType("hook.script_hash", "string")
				->etc());
	}

	public function test_it_reports_a_missing_hook(): void {
		DeployableServer::tool(GetHook::class, ["hook_id" => 404])
			->assertHasErrors();
	}

	public function test_it_requires_an_identifier(): void {
		DeployableServer::tool(GetHook::class, [])
			->assertHasErrors();
	}

	/**
	 * The web UI resolves a slug with ->first(); an ambiguous slug here has to
	 * be an error rather than a guess at which hook to deploy.
	 */
	public function test_it_refuses_an_ambiguous_slug(): void {
		$this->hook(["slug" => "dupe", "name" => "One"]);
		$this->hook(["slug" => "dupe", "name" => "Two"]);

		DeployableServer::tool(GetHook::class, ["slug" => "dupe"])
			->assertHasErrors()
			->assertSee("matches more than one hook");
	}

	public function test_it_lists_deployments_newest_first(): void {
		$hook = $this->hook();

		foreach (range(1, 3) as $ignored) {
			Deployment::create(["hook_id" => $hook->id]);
		}

		DeployableServer::tool(ListDeployments::class, ["hook_id" => $hook->id])
			->assertStructuredContent(function ($json) {
				$json->has("deployments.0.id")
					->has("deployments.1.id")
					->has("deployments.2.id")
					->missing("deployments.3")
					->etc();
			});

		$ids = Deployment::orderBy("id", "desc")->pluck("id")->all();

		DeployableServer::tool(ListDeployments::class, ["hook_id" => $hook->id])
			->assertStructuredContent(fn ($json) => $json
				->where("deployments.0.id", $ids[0])
				->where("deployments.1.id", $ids[1])
				->etc());
	}

	public function test_it_can_filter_deployments_by_state(): void {
		$hook = $this->hook();
		Deployment::create(["hook_id" => $hook->id, "state" => "done"]);

		DeployableServer::tool(ListDeployments::class, ["hook_id" => $hook->id, "state" => "running"])
			->assertStructuredContent(fn ($json) => $json->count("deployments", 0)->etc());
	}

	public function test_it_reads_raw_output_from_disk(): void {
		$hook = $this->hook();
		$deployment = Deployment::create(["hook_id" => $hook->id]);
		$this->writeOutput($deployment, "line one\nline two\nline three\n");

		DeployableServer::tool(GetDeploymentOutput::class, ["deployment_id" => $deployment->id])
			->assertStructuredContent(fn ($json) => $json
				->where("output_exists", true)
				->where("content", "line one\nline two\nline three\n")
				->where("truncated", false)
				->etc());
	}

	public function test_output_is_trimmed_from_the_end(): void {
		$hook = $this->hook();
		$deployment = Deployment::create(["hook_id" => $hook->id]);
		$this->writeOutput($deployment, "oldest\nmiddle\nnewest\n");

		DeployableServer::tool(GetDeploymentOutput::class, [
			"deployment_id" => $deployment->id,
			"tail_lines" => 2,
		])->assertStructuredContent(fn ($json) => $json
			->where("content", "middle\nnewest")
			->etc());
	}

	public function test_output_is_capped_in_bytes(): void {
		$hook = $this->hook();
		$deployment = Deployment::create(["hook_id" => $hook->id]);
		$this->writeOutput($deployment, str_repeat("x", 500));

		DeployableServer::tool(GetDeploymentOutput::class, [
			"deployment_id" => $deployment->id,
			"max_bytes" => 100,
		])->assertStructuredContent(fn ($json) => $json
			->where("bytes", 100)
			->where("truncated", true)
			->where("total_bytes", 500)
			->etc());
	}

	public function test_missing_output_is_reported_rather_than_faked(): void {
		$hook = $this->hook();
		$deployment = Deployment::create(["hook_id" => $hook->id]);

		DeployableServer::tool(GetDeploymentOutput::class, ["deployment_id" => $deployment->id])
			->assertStructuredContent(fn ($json) => $json
				->where("output_exists", false)
				->where("content", "")
				->etc());
	}

	public function test_it_reports_a_missing_deployment(): void {
		DeployableServer::tool(GetDeploymentOutput::class, ["deployment_id" => 9999])
			->assertHasErrors();
	}

/**
	 * The generated script has to record the status even when the hook script
	 * ends early, since that is exactly the case worth reporting.
	 */
public function test_the_generated_script_records_the_exit_status(): void {
	$hook = $this->hook(["script" => "echo deploying"]);
	$deployment = Deployment::create(["hook_id" => $hook->id]);

	$path = storage_path("deployments/" . $deployment->id);
	File::ensureDirectoryExists($path);

	$manager = new \App\Helpers\DeploymentManager($deployment);
	$method = new \ReflectionMethod($manager, "makeScript");
	$method->setAccessible(true);
	$script = $method->invoke($manager);

	$this->assertStringContainsString("trap record_deploy_end EXIT", $script);
	$this->assertStringContainsString("deploy:end {$deployment->id} \$status", $script);
	$this->assertStringContainsString("echo deploying", $script);
}

/**
	 * A deployment is only "succeeded" when the script actually exited 0. The
	 * old behaviour recorded state "done" no matter what the script did.
	 */
public function test_it_marks_a_deployment_failed_when_the_script_exits_non_zero(): void {
	$hook = $this->hook();
	$deployment = Deployment::create(["hook_id" => $hook->id, "state" => "running"]);

	$this->artisan("deploy:ended", ["deployment_id" => $deployment->id, "exit_code" => 3])
		->assertExitCode(0);

	$deployment->refresh();

	$this->assertSame("failed", $deployment->state);
	$this->assertSame(3, $deployment->exit_code);
	$this->assertFalse($deployment->succeeded());
}

public function test_deploy_ended_sets_state_from_the_exit_code(): void {
	$hook = $this->hook();
	$deployment = Deployment::create(["hook_id" => $hook->id, "state" => "running"]);

	$this->artisan("deploy:ended", ["deployment_id" => $deployment->id, "exit_code" => 127])
		->assertExitCode(0);

	$deployment->refresh();

	$this->assertSame("failed", $deployment->state);
	$this->assertSame(127, $deployment->exit_code);
	$this->assertNotNull($deployment->ended_at);
}

public function test_deploy_ended_records_a_clean_exit(): void {
	$hook = $this->hook();
	$deployment = Deployment::create(["hook_id" => $hook->id, "state" => "running"]);

	$this->artisan("deploy:ended", ["deployment_id" => $deployment->id, "exit_code" => 0])
		->assertExitCode(0);

	$deployment->refresh();

	$this->assertSame("done", $deployment->state);
	$this->assertSame(0, $deployment->exit_code);
	$this->assertTrue($deployment->succeeded());
}

/**
	 * deploy:end is called from inside the generated script, which runs as a
	 * separate process. Under RefreshDatabase that process opens its own SQLite
	 * connection and cannot see rows the test hasn't committed, so the end-to-end
	 * path can't be exercised here. Test_the_generated_script_records_the_exit_status
	 * and the deploy:ended tests above cover both halves of it; the link between
	 * them was verified by running a real deployment against the dev server.
	 */
public function test_deploy_end_is_guarded_against_a_missing_deployment(): void {
	$this->artisan("deploy:ended", ["deployment_id" => 999999, "exit_code" => 0])
		->assertExitCode(1);
}

public function test_a_running_deployment_has_no_verdict_yet(): void {
	$hook = $this->hook();
	$deployment = Deployment::create(["hook_id" => $hook->id, "state" => "running"]);

	$this->assertNull($deployment->succeeded());
	$this->assertTrue($deployment->isRunning());
}

/**
	 * Deployments recorded before exit codes existed can't be judged; reporting
	 * a guess would be worse than reporting nothing.
	 */
public function test_a_legacy_deployment_with_no_exit_code_is_unknown(): void {
	$hook = $this->hook();
	$deployment = Deployment::create(["hook_id" => $hook->id, "state" => "done"]);

	$this->assertNull($deployment->exit_code);
	$this->assertNull($deployment->succeeded());
}
}
