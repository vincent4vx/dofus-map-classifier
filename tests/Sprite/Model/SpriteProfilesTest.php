<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Model;

use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\SpriteProfile;
use MapClassifier\Sprite\Model\SpriteProfiles;
use MapClassifier\Sprite\Model\SpriteSet;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;
use function json_encode;

final class SpriteProfilesTest extends TestCase
{
    public function testIndexedBySetThenId(): void
    {
        $o7 = self::profile(SpriteSet::Objects, 7);
        $o3 = self::profile(SpriteSet::Objects, 3);
        $g7 = self::profile(SpriteSet::Grounds, 7);
        $profiles = SpriteProfiles::of([$o7, $o3, $g7]);

        $this->assertCount(3, $profiles);
        $this->assertSame($o7, $profiles->get(SpriteSet::Objects, 7));
        $this->assertSame($g7, $profiles->get(SpriteSet::Grounds, 7));
        $this->assertNull($profiles->get(SpriteSet::Grounds, 3));
        $this->assertSame([7 => $o7, 3 => $o3], $profiles->bySet(SpriteSet::Objects));
        $this->assertSame([$g7, $o7, $o3], iterator_to_array($profiles, false), 'Grounds first, then the order inside each set');
    }

    public function testJson(): void
    {
        $this->assertStringStartsWith('{"grounds":{},"objects":{"1":{"id":1,', (string) json_encode(SpriteProfiles::of([self::profile(SpriteSet::Objects, 1)])));
        $this->assertSame('{"grounds":{},"objects":{}}', json_encode(SpriteProfiles::of([])));
    }

    private static function profile(SpriteSet $set, int $id): SpriteProfile
    {
        return new SpriteProfile($set, $id, $set === SpriteSet::Grounds ? Layer::Ground : Layer::Layer2, 1, 1, 0, [], [], [], [], []);
    }
}
