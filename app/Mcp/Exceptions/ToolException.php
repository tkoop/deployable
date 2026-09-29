<?php

namespace App\Mcp\Exceptions;

use RuntimeException;

/**
 * A failure that is the caller's fault rather than the server's, so it is safe
 * to hand the message straight back to the client.
 */
class ToolException extends RuntimeException {
}
