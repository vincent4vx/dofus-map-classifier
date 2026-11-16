<?php

declare(strict_types=1);

namespace MapClassifier\Map\Model;

/**
 * Reason why a map cannot be loaded or rendered
 */
enum RenderError: string
{
    /** The map is encrypted and its key is not available */
    case MissingKey = 'missing_key';

    /** The map cannot be parsed or rendered */
    case RenderFailed = 'render_failed';
}
