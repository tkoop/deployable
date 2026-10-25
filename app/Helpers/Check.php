<?php

namespace App\Helpers;

/**
 * One thing Deployable looked at, and - when it's wrong - exactly how to put
 * it right.
 *
 * The shape is deliberately the same for a passing check and a failing one, so
 * the view can render a single list and a caller can ask for the blockers
 * without knowing what they are in advance.
 */
class Check {
	/**
	 * @param string $id      stable identifier, e.g. "dropin_dir"
	 * @param string $label   short name shown next to the tick or cross
	 * @param bool   $ok      did it pass?
	 * @param bool   $blocking  does a failure here stop the site being served?
	 * @param string $problem what's wrong, in plain words
	 * @param string $how     one sentence saying what the commands below do
	 * @param array<int, string> $commands  copy-and-paste shell, absolute paths
	 * @param string $verify  a command that confirms the fix took effect
	 * @param string $detail  anything worth showing verbatim, e.g. configtest output
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $label,
		public readonly bool $ok,
		public readonly bool $blocking,
		public readonly string $problem = "",
		public readonly string $how = "",
		public readonly array $commands = [],
		public readonly string $verify = "",
		public readonly string $detail = "",
	) {
	}

	public static function pass(string $id, string $label, string $detail = ""): Check {
		return new Check($id, $label, ok: true, blocking: false, detail: $detail);
	}

	public static function fail(
		string $id,
		string $label,
		string $problem,
		bool $blocking = true,
		string $how = "",
		array $commands = [],
		string $verify = "",
		string $detail = "",
	): Check {
		return new Check(
			$id,
			$label,
			ok: false,
			blocking: $blocking,
			problem: $problem,
			how: $how,
			commands: $commands,
			verify: $verify,
			detail: $detail,
		);
	}

	/**
	 * The ones that have to be fixed before anything can be served.
	 *
	 * @return array<int, Check>
	 */
	public static function blockers(array $checks): array {
		return array_values(array_filter($checks, fn (Check $check) => !$check->ok && $check->blocking));
	}

	/**
	 * The whole fix, as one block of text: what's wrong, what the commands do,
	 * the commands, and how to confirm it worked.
	 */
	public function instructions(): string {
		$parts = [$this->problem];

		if ($this->how !== "") {
			$parts[] = $this->how;
		}

		if ($this->commands) {
			$parts[] = implode("\n", $this->commands);
		}

		if ($this->detail !== "") {
			$parts[] = $this->detail;
		}

		if ($this->verify !== "") {
			$parts[] = "Check it with: " . $this->verify;
		}

		return implode("\n\n", $parts);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return [
			"id" => $this->id,
			"label" => $this->label,
			"ok" => $this->ok,
			"blocking" => $this->blocking,
			"problem" => $this->problem,
			"how" => $this->how,
			"commands" => $this->commands,
			"verify" => $this->verify,
			"detail" => $this->detail,
		];
	}
}
