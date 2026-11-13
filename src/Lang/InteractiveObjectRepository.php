<?php

declare(strict_types=1);

namespace MapClassifier\Lang;

use Arakne\Swf\SwfFile;
use ArrayAccess;
use RuntimeException;

use function is_array;
use function is_int;
use function is_iterable;
use function is_numeric;
use function is_string;
use function trim;

/**
 * Load interactive objects from the "interactiveobjects_{lang}_{version}.swf" lang file
 * The "IO" variable is read:
 * - IO.g[gfx] = interactive object id: gfx is the number of the sprite of the objects set (o*.swf)
 * - IO.d[id] = {n: name, t: type, sk: skills}
 */
final class InteractiveObjectRepository
{
    /** @var array<int, InteractiveObject>|null */
    private ?array $byGfx = null;
    private ?string $file = null;

    public function __construct(
        private readonly string $langDir,
        private readonly string $lang = 'en',
    ) {}

    /**
     * @throws RuntimeException If no lang file is found
     */
    public function file(): string
    {
        return $this->file ??= LangFile::latest($this->langDir, 'interactiveobjects', $this->lang);
    }

    /**
     * Interactive objects indexed by sprite number (of the objects set)
     *
     * @return array<int, InteractiveObject>
     *
     * @throws RuntimeException
     */
    public function byGfx(): array
    {
        return $this->byGfx ??= $this->load();
    }

    /**
     * Same as {@see byGfx()}, but return an empty array instead of failing if the lang file is not available
     *
     * @param callable(string): void $onError Called with the error message
     *
     * @return array<int, InteractiveObject>
     */
    public function tryByGfx(callable $onError): array
    {
        try {
            return $this->byGfx();
        } catch (RuntimeException $e) {
            $onError($e->getMessage());

            return [];
        }
    }

    /**
     * @return array<int, InteractiveObject>
     */
    private function load(): array
    {
        $io = (new SwfFile($this->file()))->variables()['IO'] ?? null;

        if (!$io instanceof ArrayAccess) {
            throw new RuntimeException('Invalid interactive objects lang file ' . $this->file() . ': missing IO variable');
        }

        $objects = [];

        foreach (is_iterable($io['d'] ?? null) ? $io['d'] : [] as $id => $data) {
            $name = $data instanceof ArrayAccess || is_array($data) ? ($data['n'] ?? null) : null;
            $type = $data instanceof ArrayAccess || is_array($data) ? ($data['t'] ?? null) : null;

            if (is_string($name) && trim($name) !== '') {
                $objects[(int) $id] = new InteractiveObject((int) $id, trim($name), is_int($type) || is_numeric($type) ? (int) $type : 0);
            }
        }

        $byGfx = [];

        foreach (is_iterable($io['g'] ?? null) ? $io['g'] : [] as $gfx => $id) {
            if ((is_int($id) || is_numeric($id)) && isset($objects[(int) $id])) {
                $byGfx[(int) $gfx] = $objects[(int) $id];
            }
        }

        return $byGfx;
    }
}
