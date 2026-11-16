<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Model;

use MapClassifier\Sprite\Model\InteractiveObject;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\SpriteFilter;
use MapClassifier\Sprite\Model\SpriteProfile;
use MapClassifier\Sprite\Model\SpriteProfiles;
use MapClassifier\Sprite\Model\SpriteSelection;
use MapClassifier\Sprite\Model\SpriteSet;
use PHPUnit\Framework\TestCase;

use function array_map;

final class SpriteFilterTest extends TestCase
{
    private SpriteProfiles $profiles;

    protected function setUp(): void
    {
        $this->profiles = SpriteProfiles::of([
            new SpriteProfile(SpriteSet::Objects, 1, Layer::Layer2, 10, 10, 0, [], [], [], [], []),
            new SpriteProfile(SpriteSet::Objects, 2, Layer::Layer1, 30, 30, 0, [], [], [], [], []),
            new SpriteProfile(SpriteSet::Objects, 3, Layer::Layer2, 20, 20, 0, [], [], [], [], [], new InteractiveObject(38, 'Wheat', 1)),
            new SpriteProfile(SpriteSet::Grounds, 1, Layer::Ground, 50, 50, 0, [], [], [], [], []),
        ]);
    }

    public function testFilters(): void
    {
        $this->assertSame(['g1', 'o1', 'o2', 'o3'], $this->names(new SpriteFilter()));
        $this->assertSame(['o1', 'o2', 'o3'], $this->names(new SpriteFilter(set: SpriteSet::Objects)));
        $this->assertSame(['o1', 'o3'], $this->names(new SpriteFilter(layer: Layer::Layer2)));
        $this->assertSame(['g1', 'o2'], $this->names(new SpriteFilter(minMaps: 25)));
        $this->assertSame(['o3'], $this->names(new SpriteFilter(interactiveOnly: true)));
        $this->assertSame(['g1', 'o1'], $this->names(new SpriteFilter(SpriteSelection::parse(['1']))));
        $this->assertSame(['o1'], $this->names(new SpriteFilter(SpriteSelection::parse(['1']), set: SpriteSet::Objects)));
    }

    /**
     * @return list<string>
     */
    private function names(SpriteFilter $filter): array
    {
        return array_map(static fn (SpriteProfile $profile) => $profile->set->spriteName($profile->id), $filter->filter($this->profiles));
    }
}
