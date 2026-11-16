<?php

declare(strict_types=1);

namespace MapClassifier\Map\Exception;

use MapClassifier\Map\Model\RenderError;
use RuntimeException;
use Throwable;

/**
 * Error raised when a map cannot be loaded or rendered
 */
final class RenderException extends RuntimeException
{
    public function __construct(
        public readonly RenderError $error,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
