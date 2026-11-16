<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Infrastructure\Filesystem;

use MapClassifier\Infrastructure\Filesystem\Path;
use PHPUnit\Framework\TestCase;

final class PathTest extends TestCase
{
    public function testRelativeUrl(): void
    {
        $this->assertSame('sprites', Path::relativeUrl('/app/var', '/app/var/sprites'));
        $this->assertSame('a/b', Path::relativeUrl('/app/var', '/app/var/a/b'));
        $this->assertSame('file:///other/sprites', Path::relativeUrl('/app/var', '/other/sprites'));
        $this->assertSame('file:///app/variable', Path::relativeUrl('/app/var', '/app/variable'));
    }
}
