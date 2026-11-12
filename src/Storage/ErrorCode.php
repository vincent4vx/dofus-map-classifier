<?php

declare(strict_types=1);

namespace MapClassifier\Storage;

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

    public function isRenderError(): bool
    {
        return $this === self::MissingKey || $this === self::RenderFailed;
    }
}
