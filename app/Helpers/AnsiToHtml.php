<?php

namespace App\Helpers;

/**
 * Renders a string of terminal output as HTML.  SGR escape sequences -- the
 * `ESC[...m` colour codes that artisan, composer and friends emit -- become
 * inline styles on <span> elements; every other escape sequence is dropped,
 * exactly as a terminal would leave it invisible.  Any text that is not an
 * escape sequence is HTML-escaped, so the result is safe to drop into a view
 * with {!! ... !!}.
 */
class AnsiToHtml {

	/** The classic xterm sixteen-colour palette. */
	private const COLORS = [
		"#000000", "#800000", "#008000", "#808000",
		"#000080", "#800080", "#008080", "#c0c0c0",
		"#808080", "#ff0000", "#00ff00", "#ffff00",
		"#0000ff", "#ff00ff", "#00ffff", "#ffffff",
	];

	/** The six levels the 6x6x6 colour cube uses. */
	private const CUBE = [0, 95, 135, 175, 215, 255];

	/**
	 * A whole escape sequence: cursor motion, colour, etc.  SGR first, then OSC
	 * window titles, then anything else beginning with an escape byte.  The
	 * single capture group is what PREG_SPLIT_DELIM_CAPTURE hands back to us.
	 */
	private const SEQUENCE = '/(\x1B\[[0-9;?]*[ -\/]*[@-~]|\x1B\][^\x07]*\x07|\x1B.)/';

	public static function convert($text) {
		$out = "";
		$style = [];
		$previous = "";

		$chunks = preg_split(self::SEQUENCE, $text, -1, PREG_SPLIT_DELIM_CAPTURE);

		foreach ($chunks as $chunk) {
			if ($chunk === "") {
				continue;
			}

			// A bare SGR sequence changes the running style.
			if (preg_match('/^\x1B\[(?:\d+(?:;\d+)*)?m$/', $chunk)) {
				self::apply(self::params($chunk), $style);
				continue;
			}

			// Any captured escape sequence is invisible in a terminal, so drop
			// the lot (OSC window titles included) rather than leave the ESC
			// stripped from a chunk that was never going to be useful.
			if (str_starts_with($chunk, "\x1B")) {
				continue;
			}

			// Stray escape bytes that the pattern could not consume still hide
			// in real text chunks; erase just those, never the text around them.
			$chunk = str_replace("\x1B", "", $chunk);
			if ($chunk === "") {
				continue;
			}

			// Emit (or close) a span only where the style actually changed.
			$css = self::css($style);
			if ($css !== $previous) {
				if ($previous !== "") {
					$out .= "</span>";
				}
				$previous = $css;
				if ($css !== "") {
					$out .= '<span style="' . $css . '">';
				}
			}

			$out .= htmlspecialchars($chunk, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
		}

		if ($previous !== "") {
			$out .= "</span>";
		}

		return $out;
	}

	/** "31;1m" becomes [31, 1]; a bare "m" is a reset. */
	private static function params($chunk) {
		$body = substr($chunk, 2, -1);
		if ($body === "") {
			return [0];
		}
		return array_map("intval", explode(";", $body));
	}

	/** Accumulate SGR codes into a styling dictionary. */
	private static function apply($codes, &$style) {
		$n = count($codes);
		for ($i = 0; $i < $n; $i++) {
			$code = $codes[$i];

			if ($code === 0) {
				$style = [];
			} elseif ($code === 1) {
				$style["bold"] = true;
			} elseif ($code === 2) {
				$style["dim"] = true;
			} elseif ($code === 3) {
				$style["italic"] = true;
			} elseif ($code === 4) {
				$style["underline"] = true;
			} elseif ($code === 7) {
				$style["reverse"] = true;
			} elseif ($code === 22) {
				$style["bold"] = false;
				$style["dim"] = false;
			} elseif ($code === 23) {
				$style["italic"] = false;
			} elseif ($code === 24) {
				$style["underline"] = false;
			} elseif ($code === 27) {
				$style["reverse"] = false;
			} elseif ($code === 39) {
				$style["fg"] = null;
			} elseif ($code === 49) {
				$style["bg"] = null;
			} elseif ($code >= 30 && $code <= 37) {
				$style["fg"] = self::COLORS[$code - 30];
			} elseif ($code >= 40 && $code <= 47) {
				$style["bg"] = self::COLORS[$code - 40];
			} elseif ($code >= 90 && $code <= 97) {
				$style["fg"] = self::COLORS[$code - 90 + 8];
			} elseif ($code >= 100 && $code <= 107) {
				$style["bg"] = self::COLORS[$code - 100 + 8];
			} elseif ($code === 38 || $code === 48) {
				// Extended colour: 38;5;Index or 38;2;R;G;B.
				if (($codes[$i + 1] ?? null) === 5) {
					$color = self::color256($codes[$i + 2] ?? 0);
					if ($code === 38) {
						$style["fg"] = $color;
					} else {
						$style["bg"] = $color;
					}
					$i += 2;
				} elseif (($codes[$i + 1] ?? null) === 2) {
					$r = $codes[$i + 2] ?? 0;
					$g = $codes[$i + 3] ?? 0;
					$b = $codes[$i + 4] ?? 0;
					$color = sprintf("#%02x%02x%02x", $r, $g, $b);
					if ($code === 38) {
						$style["fg"] = $color;
					} else {
						$style["bg"] = $color;
					}
					$i += 4;
				}
			}
		}
	}

	private static function color256($index) {
		if ($index < 16) {
			return self::COLORS[max(0, $index)];
		}
		if ($index < 232) {
			$i = $index - 16;
			$r = self::CUBE[intdiv($i, 36)];
			$g = self::CUBE[intdiv($i, 6) % 6];
			$b = self::CUBE[$i % 6];
			return sprintf("#%02x%02x%02x", $r, $g, $b);
		}
		$v = 8 + max(0, $index - 232) * 10;
		return sprintf("#%02x%02x%02x", $v, $v, $v);
	}

	/** Turn the styling dictionary into a CSS style string ("" for defaults). */
	private static function css($style) {
		// Reverse video swaps the two at display time.
		$fg = $style["fg"] ?? null;
		$bg = $style["bg"] ?? null;
		if (($style["reverse"] ?? false) && ($fg !== null || $bg !== null)) {
			[$fg, $bg] = [$bg, $fg];
		}

		$parts = [];
		if ($fg !== null) {
			$parts[] = "color:" . $fg;
		}
		if ($bg !== null) {
			$parts[] = "background-color:" . $bg;
		}
		if (($style["bold"] ?? false) === true) {
			$parts[] = "font-weight:700";
		}
		if (($style["dim"] ?? false) === true) {
			$parts[] = "opacity:.65";
		}
		if (($style["italic"] ?? false) === true) {
			$parts[] = "font-style:italic";
		}
		if (($style["underline"] ?? false) === true) {
			$parts[] = "text-decoration:underline";
		}

		return implode(";", $parts);
	}
}