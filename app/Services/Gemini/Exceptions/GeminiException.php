<?php

namespace App\Services\Gemini\Exceptions;

use RuntimeException;

/**
 * Raised when the AI assistant cannot fulfil a request (not configured, upstream
 * error, empty response). The UI catches this and shows a friendly message.
 */
class GeminiException extends RuntimeException
{
}
