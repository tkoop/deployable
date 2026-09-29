<?php

namespace App\Mcp\Support;

use App\Mcp\Exceptions\ToolException;
use App\Models\Deployment;
use App\Models\Hook;
use Carbon\Carbon;
use DateTimeInterface;
use Exception;

/**
 * Shared lookups and serialisation for the MCP tools and resources.
 *
 * These deliberately return plain arrays: MCP responses are data, and the
 * blade views have their own rendering.
 */
class Hooks {
	/**
	 * Find a hook by id, or by slug when no id was given.
	 *
	 * A slug is not unique in the database, and HookController::run() resolves
	 * one with ->first(). Guessing there would deploy the wrong project, so an
	 * ambiguous slug is an error rather than a coin flip.
	 *
	 * @throws ToolException
	 */
	public static function resolve(int|null $hookId, string|null $slug): Hook {
		if ($hookId !== null) {
			$hook = Hook::find($hookId);

			if ($hook == null) {
				throw new ToolException("There's no hook with id {$hookId}.");
			}

			return $hook;
		}

		if ($slug == null || trim($slug) === "") {
			throw new ToolException("Give me either a hook_id or a slug to work with.");
		}

		$matches = Hook::where("slug", $slug)->orderBy("id")->get();

		if ($matches->isEmpty()) {
			throw new ToolException("There's no hook with the slug '{$slug}'.");
		}

		if ($matches->count() > 1) {
			$ids = $matches->pluck("id")->implode(", ");
			throw new ToolException(
				"The slug '{$slug}' matches more than one hook (ids {$ids}). Slugs aren't " .
				"unique in this app, so use hook_id instead."
			);
		}

		return $matches->first();
	}

	/**
	 * The hook that already owns this slug, if it isn't the one being updated.
	 */
	public static function slugOwner(string $slug, int|null $exceptId = null): Hook|null {
		return Hook::where("slug", $slug)
			->when($exceptId !== null, fn ($query) => $query->where("id", "!=", $exceptId))
			->orderBy("id")
			->first();
	}

	/**
	 * A short digest of a script body.
	 *
	 * Scripts are the one thing here that gets executed, so writes carry the
	 * digest of what the caller believes they are overwriting. Without it a
	 * stale read silently discards an edit made in the browser since.
	 */
	public static function scriptHash(string $script): string {
		return substr(hash("sha256", $script), 0, 12);
	}

	/**
	 * Confirm the caller has seen the current script before overwriting it.
	 *
	 * @throws ToolException
	 */
	public static function assertHashMatches(Hook $hook, string $expected, string $toolName): void {
		$actual = self::scriptHash($hook->script);

		if ($expected === "" || $expected === null) {
			throw new ToolException(
				"{$toolName} needs expected_script_hash so it doesn't clobber a script you haven't read. " .
				"Call get_hook first and pass back its script_hash (currently {$actual})."
			);
		}

		if (!hash_equals($actual, $expected)) {
			throw new ToolException(
				"The script has changed since you last read it. get_hook reports script_hash {$actual}, " .
				"but you sent {$expected}. Re-read the hook and retry."
			);
		}
	}

	/**
	 * Mirror the normalisation HookController::doEdit() applies, so a script
	 * written over MCP and one saved through the form are stored the same way.
	 */
	public static function normaliseScript(string $script): string {
		$script = str_replace("\r", "", $script);

		if ($script !== "" && !str_ends_with($script, "\n")) {
			$script .= "\n";
		}

		return $script;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function summary(Hook $hook): array {
		return [
			"id" => $hook->id,
			"name" => $hook->name,
			"slug" => $hook->slug,
			"directory" => $hook->directory,
			// url() strips the trailing slash, so add it back before the slug.
			"webhook_url" => url("/run") . "/" . $hook->slug,
			"deployment_count" => $hook->deployments()->count(),
			"last_deployed_at" => self::timestamp($hook->lastDeployTime()),
			"script_hash" => self::scriptHash($hook->script),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function detail(Hook $hook): array {
		return self::summary($hook) + [
			"script" => $hook->script,
			"created_at" => self::timestamp($hook->created_at),
			"updated_at" => self::timestamp($hook->updated_at),
			"env_file_path" => $hook->envFile()->path() ?: null,
			"env_directory_exists" => $hook->envFile()->directoryExists(),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function deploymentSummary(Deployment $deployment): array {
		$started = self::asCarbon($deployment->created_at);
		$ended = self::asCarbon($deployment->ended_at);

		return [
			"id" => $deployment->id,
			"hook_id" => $deployment->hook_id,
			"hook_name" => $deployment->hook?->name,
			"state" => $deployment->state,
			"succeeded" => $deployment->succeeded(),
			"exit_code" => $deployment->exit_code,
			"created_at" => self::timestamp($started),
			"ended_at" => self::timestamp($ended),
			"duration_seconds" => ($started && $ended) ? (int) $started->diffInSeconds($ended) : null,
		];
	}

	/**
	 * @return Carbon|null
	 */
	private static function asCarbon(mixed $value): Carbon|null {
		if ($value == null) return null;

		if ($value instanceof Carbon) return $value;

		try {
			return Carbon::parse($value);
		} catch (Exception) {
			return null;
		}
	}

	/**
	 * ISO 8601, or null.
	 *
	 * Neither model declares $casts, so only Deployment::created_at is a
	 * Carbon (via getCreatedAtAttribute, which also pins it to
	 * America/Winnipeg); ended_at and every updated_at arrive as raw strings
	 * out of SQLite and need parsing.
	 */
	public static function timestamp(mixed $value): string|null {
		if ($value == null) return null;

		if ($value instanceof DateTimeInterface) {
			return $value->format(DateTimeInterface::ATOM);
		}

		try {
			return Carbon::parse($value)->format(\DateTimeInterface::ATOM);
		} catch (Exception) {
			return null;
		}
	}
}
