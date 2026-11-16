<?php

declare(strict_types=1);

namespace MapClassifier\Infrastructure\OpenAI;

use JsonException;
use Throwable;

use function is_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Result of a Responses API call with a structured (JSON) output: either the decoded output, or a failure
 */
final readonly class StructuredResponse
{
    private function __construct(
        /** @var array<array-key, mixed>|null Decoded output, null on failure */
        public ?array $output,
        public ?ResponseFailure $failure,
        /** Failure message, null on success */
        public ?string $message,
        /** Model which answered, or the requested model if unknown */
        public string $model,
    ) {}

    /**
     * @param int $status HTTP status (0 on transport error)
     * @param array<array-key, mixed> $body Response body
     * @param Throwable|null $error Transport error
     * @param string $model Requested model, used if the response does not give it
     */
    public static function parse(int $status, array $body, ?Throwable $error, string $model): self
    {
        if ($error !== null) {
            return self::failure(ResponseFailure::RequestFailed, $error->getMessage(), $model);
        }

        if ($status !== 200) {
            return self::failure(ResponseFailure::RequestFailed, ResponseOutput::error($status, $body), $model);
        }

        $output = ResponseOutput::fromBody($body);
        $model = $output->model ?? $model;

        if ($output->refusal !== null) {
            return self::failure(ResponseFailure::Refused, $output->refusal, $model);
        }

        if ($output->text === null) {
            return self::failure(ResponseFailure::NoOutput, 'No output text (response status: ' . $output->status . ')', $model);
        }

        try {
            $data = json_decode($output->text, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return self::invalidOutput($e->getMessage(), $model);
        }

        return new self(is_array($data) ? $data : [], null, null, $model);
    }

    /**
     * Failure of an output which does not match the expected data
     */
    public static function invalidOutput(string $message, string $model): self
    {
        return self::failure(ResponseFailure::InvalidOutput, 'Invalid output: ' . $message, $model);
    }

    private static function failure(ResponseFailure $failure, string $message, string $model): self
    {
        return new self(null, $failure, $message, $model);
    }
}
