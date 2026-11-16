<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Batch\Model;

/**
 * Summary of a batch import
 */
final class ImportReport
{
    public int $ok = 0;
    public int $errors = 0;

    /** @var list<int> Maps of the batch without any result line: they stay unclassified */
    public array $missing = [];
}
