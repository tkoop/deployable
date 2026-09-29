<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Mint a Sanctum token for the MCP server without needing the web UI.
 *
 * Handy for bootstrapping, and for recovery when the API tokens page is
 * unreachable.
 */
class McpToken extends Command {
	protected $signature = "mcp:token
		{name=mcp : A label for the token, so you can tell them apart later}
		{--abilities=* : Sanctum abilities to grant; defaults to unrestricted}";

	protected $description = "Create an API token for the MCP server";

	public function handle(): int {
		$user = $this->admin();

		if ($user == null) {
			$this->error("No admin user exists. Log in through the web UI first, then run this again.");

			return self::FAILURE;
		}

		$name = (string) $this->argument("name");
		$abilities = $this->option("abilities");
		$token = $user->createToken($name, $abilities ?: ["*"]);

		$this->newLine();
		$this->line("  <options=bold>Token created for {$user->name}</> ({$name})");
		$this->newLine();
		$this->line("  {$token->plainTextToken}");
		$this->newLine();
		$this->comment("  This is the only time it will be shown. Send it to your MCP client as:");
		$this->line("      Authorization: Bearer <token>");
		$this->newLine();

		return self::SUCCESS;
	}

	/**
	 * The admin user is only ever created by the first successful web login,
	 * which authenticates against ADMIN_PASSWORD rather than the users table.
	 * Mirror that here so this works before anyone has logged in.
	 */
	private function admin(): User|null {
		$user = User::first();

		if ($user != null) {
			return $user;
		}

		$this->warn("No admin user exists yet, so one was created for you.");

		return User::create(["name" => "Admin"]);
	}
}
