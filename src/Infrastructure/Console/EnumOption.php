<?php

declare(strict_types=1);

namespace MapClassifier\Infrastructure\Console;

use BackedEnum;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;

use function array_map;
use function implode;
use function sprintf;

/**
 * Parse a console option into a backed enum
 */
final class EnumOption
{
    /**
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T|null Null if the option is not given
     *
     * @throws InvalidArgumentException When the value is not a case of the enum
     */
    public static function get(InputInterface $input, string $name, string $enum): ?BackedEnum
    {
        $value = $input->getOption($name);

        if ($value === null) {
            return null;
        }

        return $enum::tryFrom((string) $value) ?? throw new InvalidArgumentException(sprintf(
            'Invalid --%s "%s", expected one of: %s',
            $name,
            $value,
            implode(', ', self::values($enum)),
        ));
    }

    /**
     * @param class-string<BackedEnum> $enum
     *
     * @return list<string>
     */
    public static function values(string $enum): array
    {
        return array_map(static fn (BackedEnum $case) => (string) $case->value, $enum::cases());
    }
}
