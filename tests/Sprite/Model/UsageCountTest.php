<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Model;

use MapClassifier\Sprite\Model\UsageCount;
use PHPUnit\Framework\TestCase;

final class UsageCountTest extends TestCase
{
    public function testWithMap(): void
    {
        $usage = new UsageCount()->withMap(3)->withMap(5);

        $this->assertEquals(new UsageCount(2, 8), $usage);
        $this->assertSame(4.0, $usage->perMap());
        $this->assertSame(0.0, new UsageCount()->perMap());
    }

    public function testJson(): void
    {
        $this->assertSame(['maps' => 2, 'occurrences' => 8], new UsageCount(2, 8)->jsonSerialize());
        $this->assertEquals(new UsageCount(2, 8), UsageCount::fromJson(['maps' => 2, 'occurrences' => 8]));
        $this->assertEquals(new UsageCount(3), UsageCount::fromJson(3), 'Format without occurrences');
        $this->assertEquals(new UsageCount(), UsageCount::fromJson(null));
    }
}
