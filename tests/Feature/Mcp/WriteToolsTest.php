<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\DeployableServer;
use App\Mcp\Tools\AppendToHookScript;
use App\Mcp\Tools\CreateHook;
use App\Mcp\Tools\DeleteHook;
use App\Mcp\Tools\GetHook;
use App\Mcp\Tools\UpdateHook;
use App\Models\Deployment;
use App\Models\Hook;
use App\Mcp\Support\Hooks as HooksSupport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WriteToolsTest extends TestCase {
	use RefreshDatabase;

	protected function hook(array $attributes = []): Hook {
		return Hook::create($attributes + [
			"name" => "Example",
			"slug" => "example",
			"script" => "echo hi",
		]);
	}

	public function test_it_creates_a_hook(): void {
		DeployableServer::tool(CreateHook::class, [
			"name" => "New Hook",
			"slug" => "new-hook",
			"script" => "echo created",
		])->assertOk()
			->assertHasNoErrors()
			->assertStructuredContent(fn ($json) => $json
				->where("hook.name", "New Hook")
				->where("hook.slug", "new-hook")
				->where("created", true)
				->etc());

		$this->assertDatabaseHas("hooks", ["slug" => "new-hook"]);
	}

	public function test_a_created_script_gets_a_trailing_newline(): void {
		DeployableServer::tool(CreateHook::class, [
			"name" => "New Hook",
			"slug" => "new-hook",
			"script" => "echo created",
		]);

		$this->assertSame("echo created\n", Hook::where("slug", "new-hook")->value("script"));
	}

	/**
	 * The web form never checked this, but a duplicate slug silently makes one
	 * of the two unreachable at its webhook URL.
	 */
	public function test_it_refuses_a_duplicate_slug_on_create(): void {
		$this->hook(["slug" => "taken"]);

		DeployableServer::tool(CreateHook::class, ["name" => "Other", "slug" => "taken"])
			->assertHasErrors()
			->assertSee("already uses the slug");

		$this->assertSame(1, Hook::where("slug", "taken")->count());
	}

	public function test_it_requires_an_absolute_directory(): void {
		DeployableServer::tool(CreateHook::class, [
			"name" => "Bad",
			"slug" => "bad",
			"directory" => "relative/path",
		])->assertHasErrors();
	}

	public function test_it_updates_only_the_fields_it_was_given(): void {
		$hook = $this->hook(["directory" => "/var/www/example"]);

		DeployableServer::tool(UpdateHook::class, [
			"hook_id" => $hook->id,
			"name" => "Renamed",
		])->assertOk()
			->assertStructuredContent(fn ($json) => $json
				->where("hook.name", "Renamed")
				->where("hook.slug", "example")
				->where("hook.directory", "/var/www/example")
				->where("changed", ["name"])
				->etc());
	}

	public function test_writing_a_script_requires_the_current_hash(): void {
		$hook = $this->hook();

		DeployableServer::tool(UpdateHook::class, [
			"hook_id" => $hook->id,
			"script" => "echo overwritten",
		])->assertHasErrors()
			->assertSee("expected_script_hash");

		$this->assertSame("echo hi", $hook->fresh()->script);
	}

	public function test_a_stale_hash_refuses_the_write(): void {
		$hook = $this->hook();

		DeployableServer::tool(UpdateHook::class, [
			"hook_id" => $hook->id,
			"script" => "echo overwritten",
			"expected_script_hash" => "000000000000",
		])->assertHasErrors()
			->assertSee("has changed since you last read it");

		$this->assertSame("echo hi", $hook->fresh()->script);
	}

	public function test_it_replaces_a_script_with_the_right_hash(): void {
		$hook = $this->hook();

		DeployableServer::tool(UpdateHook::class, [
			"hook_id" => $hook->id,
			"script" => "echo replaced",
			"expected_script_hash" => HooksSupport::scriptHash($hook->script),
		])->assertOk()
			->assertHasNoErrors()
			->assertStructuredContent(fn ($json) => $json
				->where("hook.script", "echo replaced\n")
				->where("changed", ["script"])
				->etc());

		$this->assertSame("echo replaced\n", $hook->fresh()->script);
	}

	public function test_it_refuses_a_slug_that_collides_with_another_hook(): void {
		$this->hook(["slug" => "taken"]);
		$other = $this->hook(["slug" => "mine"]);

		DeployableServer::tool(UpdateHook::class, [
			"hook_id" => $other->id,
			"new_slug" => "taken",
		])->assertHasErrors()
			->assertSee("already uses the slug");

		$this->assertSame("mine", $other->fresh()->slug);
	}

	public function test_a_hook_can_keep_its_own_slug(): void {
		$hook = $this->hook(["slug" => "stable"]);

		DeployableServer::tool(UpdateHook::class, [
			"hook_id" => $hook->id,
			"new_slug" => "stable",
		])->assertOk()
			->assertHasNoErrors();

		$this->assertSame("stable", $hook->fresh()->slug);
	}

	public function test_it_appends_to_a_script(): void {
		$hook = $this->hook();

		DeployableServer::tool(AppendToHookScript::class, [
			"hook_id" => $hook->id,
			"lines" => "echo second",
			"expected_script_hash" => HooksSupport::scriptHash($hook->script),
		])->assertOk()
			->assertHasNoErrors()
			->assertStructuredContent(fn ($json) => $json
				->where("lines_added", ["echo second"])
				->etc());

		$this->assertSame("echo hi\necho second\n", $hook->fresh()->script);
	}

	/**
	 * The separator has to be one newline whether or not the stored script
	 * already ended with one.
	 */
	public function test_appending_does_not_add_a_blank_line(): void {
		$hook = $this->hook(["script" => "echo hi\n"]);

		DeployableServer::tool(AppendToHookScript::class, [
			"hook_id" => $hook->id,
			"lines" => "echo second",
			"expected_script_hash" => HooksSupport::scriptHash($hook->script),
		])->assertOk();

		$this->assertSame("echo hi\necho second\n", $hook->fresh()->script);
	}

	public function test_appending_requires_the_hash(): void {
		$hook = $this->hook();

		DeployableServer::tool(AppendToHookScript::class, [
			"hook_id" => $hook->id,
			"lines" => "echo second",
		])->assertHasErrors();

		$this->assertSame("echo hi", $hook->fresh()->script);
	}

	public function test_the_hash_rotates_after_a_write(): void {
		$hook = $this->hook();
		$before = HooksSupport::scriptHash($hook->script);

		DeployableServer::tool(AppendToHookScript::class, [
			"hook_id" => $hook->id,
			"lines" => "echo second",
			"expected_script_hash" => $before,
		])->assertOk();

		$after = HooksSupport::scriptHash($hook->fresh()->script);

		$this->assertNotSame($before, $after);

		// The value the caller was just handed must not still be accepted.
		DeployableServer::tool(AppendToHookScript::class, [
			"hook_id" => $hook->id,
			"lines" => "echo third",
			"expected_script_hash" => $before,
		])->assertHasErrors();
	}

	public function test_it_deletes_a_hook(): void {
		$hook = $this->hook();

		DeployableServer::tool(DeleteHook::class, ["hook_id" => $hook->id])
			->assertOk()
			->assertStructuredContent(fn ($json) => $json
				->where("deleted", true)
				->where("deployments_deleted", 0)
				->etc());

		$this->assertDatabaseMissing("hooks", ["id" => $hook->id]);
	}

	/**
	 * deployments.hook_id is a cascading foreign key, so deleting a hook takes
	 * its deployment history with it. The tool has to say so.
	 */
	public function test_deleting_a_hook_cascades_to_its_deployments(): void {
		$hook = $this->hook();
		Deployment::create(["hook_id" => $hook->id]);
		Deployment::create(["hook_id" => $hook->id]);

		DeployableServer::tool(DeleteHook::class, ["hook_id" => $hook->id])
			->assertStructuredContent(fn ($json) => $json
				->where("deployments_deleted", 2)
				->etc());

		$this->assertDatabaseMissing("deployments", ["hook_id" => $hook->id]);
	}

	public function test_it_reports_a_missing_hook_on_delete(): void {
		DeployableServer::tool(DeleteHook::class, ["hook_id" => 404])
			->assertHasErrors();
	}

	/**
	 * The hash an agent is handed by get_hook has to be the one the write tools
	 * accept, otherwise the whole guard is unusable.
	 */
	public function test_the_hash_from_get_hook_is_the_one_write_accepts(): void {
		$hook = $this->hook();

		$hash = null;

		DeployableServer::tool(GetHook::class, ["hook_id" => $hook->id])
			->assertOk()
			->assertStructuredContent(function ($json) use (&$hash) {
				$hash = $json->toArray()["hook"]["script_hash"] ?? null;
				$json->etc();
			});

		$this->assertNotNull($hash);

		DeployableServer::tool(AppendToHookScript::class, [
			"hook_id" => $hook->id,
			"lines" => "echo appended",
			"expected_script_hash" => $hash,
		])->assertOk()->assertHasNoErrors();
	}
}
