<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Model;

/**
 * Reason why a sprite has no type
 */
enum SpriteTypeError: string
{
    /** The sprite image is not exported (see sprites:images) */
    case ImageNotExported = 'image_not_exported';

    /** The model or the API returned an error, or an invalid response */
    case ClassificationFailed = 'classification_failed';

    /** The model refused to classify the sprite */
    case Refused = 'refused';
}
