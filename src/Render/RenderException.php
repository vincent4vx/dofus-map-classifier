<?php

declare(strict_types=1);

namespace MapClassifier\Render;

use MapClassifier\Storage\ErrorCode;
use RuntimeException;
use Throwable;

/**
 * Error raised when a map cannot be rendered
 */
final class RenderException extends RuntimeException
{
    public function __construct(
        public readonly ErrorCode $errorCode,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
