<?php

namespace App\Mcp\Support;

use App\Models\Deployment;

/**
	 * Reading deployment output for MCP clients.
 *
	 * State questions are answered by the Deployment model itself; this only
	 * covers the output file, which the web UI reads but keeps to itself.
	 */
class Deployments {
	public const DEFAULT_MAX_BYTES = 65536;

	/**
	 * DeploymentManager keeps this private and only ever renders it to HTML,
	 * which is no use to an agent. Output is read from disk directly.
	 *
	 * Note the deployments.output column is vestigial: nothing has ever
	 * written to it, so it is not a source.
	 */
	public static function outputPath(Deployment $deployment): string {
		return storage_path("deployments/" . $deployment->id . "/output.txt");
	}

	/**
	 * Raw output, optionally trimmed.
	 *
	 * ANSI escape codes are left intact — the deploy script exports NO_COLOR for
	 * this app's own artisan, but third-party commands in the hook script can
	 * still emit colour.
	 *
	 * Trimming takes from the end of the log, since for a running or recent
	 * deployment the last lines are the interesting ones, and an unbounded
	 * log would otherwise crowd out the rest of the conversation.
	 *
	 * @return array{content: string, path: string, exists: bool, bytes: int, total_bytes: int, truncated: bool}
	 */
	public static function readOutput(Deployment $deployment, int|null $tailLines = null, int|null $maxBytes = null): array {
		$path = self::outputPath($deployment);

		if (!is_file($path)) {
			return [
				"content" => "",
				"path" => $path,
				"exists" => false,
				"bytes" => 0,
				"total_bytes" => 0,
				"truncated" => false,
			];
		}

		$contents = (string) file_get_contents($path);
		$totalBytes = strlen($contents);

		if ($tailLines !== null && $tailLines > 0) {
			$lines = preg_split('/\R/', $contents);

			// A trailing newline splits into a final empty element, which would
			// otherwise count against tail_lines and drop a real line.
			if (count($lines) > 1 && end($lines) === "") {
				array_pop($lines);
			}

			$contents = implode("\n", array_slice($lines, -$tailLines));
		}

		$limit = $maxBytes ?? self::DEFAULT_MAX_BYTES;
		$truncated = false;

		if ($limit > 0 && strlen($contents) > $limit) {
			$contents = substr($contents, -$limit);
			$truncated = true;
		}

		return [
			"content" => $contents,
			"path" => $path,
			"exists" => true,
			"bytes" => strlen($contents),
			"total_bytes" => $totalBytes,
			"truncated" => $truncated,
		];
	}

	/**
	 * @see Deployment::isRunning()
	 */
	public static function isRunning(Deployment $deployment): bool {
		return $deployment->isRunning();
	}
}
