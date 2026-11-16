<?php

declare(strict_types=1);

namespace MapClassifier\Infrastructure\OpenAI;

/**
 * Reason why a Responses API call has no usable output
 */
enum ResponseFailure
{
    /** Transport error, or HTTP error returned by the API */
    case RequestFailed;

    /** The model refused to answer */
    case Refused;

    /** The response has no output text (e.g. incomplete response) */
    case NoOutput;

    /** The output text is not valid JSON, or does not match the expected data */
    case InvalidOutput;
}
