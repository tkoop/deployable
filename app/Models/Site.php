<?php

namespace App\Models;

use App\Helpers\Preflight;
use App\Helpers\Vhost;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An Apache site, which always belongs to exactly one hook.
 *
 * There's deliberately no page listing every site on the server: a site is a
 * property of the project that serves it, the way a .env file is. Plenty of
 * vhosts on this machine won't appear here at all, and any of them can be
 * brought across by copying its config into the drop-in directory.
 */
class Site extends Model {
	protected $guarded = [];

	protected $casts = [
		"enabled" => "boolean",
		"aliases" => "array",
	];

	public function hook(): BelongsTo {
		return $this->belongsTo(Hook::class);
	}

	public function apache(): Vhost {
		return Vhost::forSite($this);
	}

	/**
	 * Everything that has to be true for this site to be served, each with the
	 * commands that fix it when it isn't.
	 *
	 * @return array<int, \App\Helpers\Check>
	 */
	public function preflight(): array {
		return Preflight::forSite($this);
	}

	/**
	 * Whether Apache should be serving https for this site. Ticking the SSL box
	 * isn't the same as holding a certificate - ssl_state only turns to
	 * "active" once certbot has written one and the vhost is in place, and it
	 * sits at "pending" or "failed" for everything in between.
	 */
	public function sslActive(): bool {
		return $this->ssl_state === "active";
	}

	/**
	 * Whether SSL has been asked for at all, in any state.
	 */
	public function sslWanted(): bool {
		return $this->ssl_state !== "none";
	}

	public function vhostPath(): string {
		return $this->apache()->path();
	}

	public function sslVhostPath(): string {
		return $this->apache()->sslPath();
	}

	/**
	 * Where Apache writes this site's PHP/Apache error log, as declared by
	 * the ErrorLog directive in the vhost template.  Reading it is what the
	 * "PHP Error Log" button does.
	 */
	public function errorLogPath(): string {
		return $this->apache()->errorLogPath();
	}

	public function sslErrorLogPath(): string {
		return $this->apache()->sslErrorLogPath();
	}

	/**
	 * Alias kept for callers that ask for the "PHP" log rather than the
	 * Apache one - both are the same file for a proxied PHP site.
	 */
	public function phpErrorLogPath(): string {
		return $this->errorLogPath();
	}

	public function logPath(): string {
		return $this->errorLogPath();
	}

	/**
	 * All the host names this site answers on, primary domain first.
	 *
	 * @return array<int, string>
	 */
	public function allHosts(): array {
		$hosts = [$this->domain];

		foreach ($this->aliases ?? [] as $alias) {
			$alias = trim((string) $alias);

			if ($alias !== "" && !in_array($alias, $hosts, true)) {
				$hosts[] = $alias;
			}
		}

		return $hosts;
	}

	/**
	 * The aliases as a newline-separated string, for editing in a textarea.
	 */
	public function aliasesText(): string {
		return implode("\n", $this->aliases ?? []);
	}

	/**
	 * The certificate's own view of things, in the words of whoever last ran
	 * certbot. Root writes this, so it's the truth rather than a guess.
	 */
	public function sslResult(): array|null {
		$path = rtrim(config("deployable.ssl_result_dir"), "/") . "/{$this->domain}.json";

		if (!is_file($path)) {
			return null;
		}

		$data = json_decode((string) @file_get_contents($path), true);

		return is_array($data) ? $data : null;
	}
}
