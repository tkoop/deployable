<?php

namespace App\Helpers;

/**
 * Reads the Apache vhost files that are already on this server.
 *
 * Deployable doesn't manage most of them - it never has - so the only thing it
 * does with one is show you where it lives, what's in it, and the commands to
 * copy it into the drop-in directory where Deployable can take over. Nothing
 * here writes, moves or deletes anything.
 */
class ExistingVhosts {
	/**
	 * Every vhost config Apache could load, from both places it looks.
	 *
	 * @return array<int, array{
	 *     file: string,
	 *     basename: string,
	 *     domain: string,
	 *     document_root: string,
	 *     in_dropin: bool,
	 *     enabled: bool,
	 *     ssl: bool
	 * }>
	 */
	public static function all(): array {
		$found = [];

		foreach (self::locations() as $in_dropin => $directory) {
			foreach (glob(rtrim($directory, "/") . "/*.conf") ?: [] as $file) {
				$contents = @file_get_contents($file);
				if ($contents === false) {
					continue;
				}

				$domain = self::directive($contents, "ServerName");
				if ($domain === null || $domain === "") {
					continue;
				}

				$found[] = [
					"file" => $file,
					"basename" => basename($file),
					"domain" => $domain,
					"document_root" => self::directive($contents, "DocumentRoot") ?: "",
					"in_dropin" => $in_dropin,
					"enabled" => $in_dropin || self::isEnabled(basename($file)),
					"ssl" => str_contains($contents, "SSLCertificateFile"),
				];
			}
		}

		usort($found, fn ($a, $b) => [$a["domain"], $a["basename"]] <=> [$b["domain"], $b["basename"]]);

		return $found;
	}

	/**
	 * Where else this domain is already declared, if anywhere. A duplicate
	 * ServerName is only a warning to Apache, so the first vhost loaded wins
	 * and the other one silently never serves anything - which is why this is
	 * a blocker rather than a shrug.
	 *
	 * @param string $domain
	 * @param array<int, string> $ignore files that belong to the site being edited
	 * @return string|null the offending file
	 */
	public static function ownerOf(string $domain, array $ignore = []): string|null {
		foreach (self::all() as $vhost) {
			if (strcasecmp($vhost["domain"], $domain) !== 0) {
				continue;
			}

			if (in_array($vhost["file"], $ignore, true)) {
				continue;
			}

			return $vhost["file"];
		}

		return null;
	}

	/**
	 * The commands that move an existing vhost under Deployable's control.
	 *
	 * The order matters: disable first so Apache stops loading the old copy
	 * before the new one appears, then copy. The second the file lands in the
	 * drop-in directory the path unit picks it up, validates it and reloads -
	 * there's deliberately no "now reload Apache" step for you to miss.
	 *
	 * @param array{file: string, basename: string, in_dropin: bool, enabled: bool, ssl: bool} $vhost
	 * @return array<int, string>
	 */
	public static function instructions(array $vhost): array {
		$available = config("deployable.sites_available");
		$dropin = config("deployable.vhost_dir");

		if ($vhost["in_dropin"]) {
			return [];
		}

		$commands = [];

		// An SSL site is two files: the :80 one and its -le-ssl partner.
		$partners = [$vhost["basename"]];
		if ($vhost["ssl"]) {
			$partners[] = preg_replace('/\.conf$/', '-le-ssl.conf', $vhost["basename"]);
		}

		foreach ($partners as $name) {
			$source = rtrim($available, "/") . "/" . $name;

			if (!is_file($source)) {
				continue;
			}

			if ($vhost["enabled"] && is_link(rtrim(config("deployable.sites_enabled"), "/") . "/" . $name)) {
				$commands[] = "sudo a2dissite {$name}";
			}

			$commands[] = "sudo cp {$source} {$dropin}/";
		}

		return $commands;
	}

	/**
	 * The document root of a file we may not be able to parse completely;
	 * an empty string means "we couldn't tell".
	 */
	public static function documentRootOf(string $file): string {
		$contents = @file_get_contents($file);

		return $contents === false ? "" : (self::directive($contents, "DocumentRoot") ?: "");
	}

	/**
	 * The first ServerName or DocumentRoot in a config, quotes and all.
	 */
	private static function directive(string $contents, string $name): string|null {
		if (preg_match('/^\s*' . preg_quote($name, '/') . '\s+"?([^"\n]+)"?\s*$/mi', $contents, $matches)) {
			return trim($matches[1]);
		}

		return null;
	}

	private static function isEnabled(string $basename): bool {
		return is_link(rtrim(config("deployable.sites_enabled"), "/") . "/" . $basename);
	}

	/**
	 * @return array<bool, string> directory, keyed by whether it's the one
	 *                              Deployable writes to
	 */
	private static function locations(): array {
		return [
			true => config("deployable.vhost_dir"),
			false => config("deployable.sites_available"),
		];
	}
}
