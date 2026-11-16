<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Model;

use MapClassifier\Map\Model\RenderError;

/**
 * Reason why a map has no classification
 */
enum ErrorCode: string
{
    /** The map is encrypted and its key is not available */
    case MissingKey = 'missing_key';

    /** The map cannot be parsed or rendered */
    case RenderFailed = 'render_failed';

    /** The model or the API returned an error, or an invalid response */
    case ClassificationFailed = 'classification_failed';

    /** The model refused to classify the map */
    case Refused = 'refused';

    /**
     * Error code of a map which cannot be loaded or rendered
     */
    public static function fromRenderError(RenderError $error): self
    {
        return match ($error) {
            RenderError::MissingKey => self::MissingKey,
            RenderError::RenderFailed => self::RenderFailed,
        };
    }

    public function isRenderError(): bool
    {
        return $this === self::MissingKey || $this === self::RenderFailed;
    }
}
