<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Classification\Service;

use MapClassifier\Sprite\Classification\Model\SpriteCategory;
use MapClassifier\Sprite\Classification\Model\SpriteType;
use MapClassifier\Sprite\Classification\Model\SpriteTypeError;
use MapClassifier\Sprite\Classification\Model\SpriteTypeResult;
use MapClassifier\Sprite\Classification\Repository\SpriteTypeRepository;
use MapClassifier\Sprite\Classification\Service\SpritesToClassify;
use MapClassifier\Sprite\Model\InteractiveObject;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\RenderedSprite;
use MapClassifier\Sprite\Model\SpriteFilter;
use MapClassifier\Sprite\Model\SpriteProfile;
use MapClassifier\Sprite\Model\SpriteProfiles;
use MapClassifier\Sprite\Model\SpriteSelection;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Sprite\Repository\SpriteImageRepository;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;

use function array_map;

final class SpritesToClassifyTest extends TestCase
{
    use TempDirTrait;

    private SpriteTypeRepository $types;
    private SpritesToClassify $service;
    private SpriteProfiles $profiles;

    protected function setUp(): void
    {
        $images = new SpriteImageRepository($this->tempDir() . '/sprites');
        $this->types = new SpriteTypeRepository($this->tempDir() . '/types');
        $this->service = new SpritesToClassify($this->types, $images);

        $this->profiles = SpriteProfiles::of([
            self::profile(SpriteSet::Grounds, 1, Layer::Ground, 50),
            self::profile(SpriteSet::Objects, 1, Layer::Layer2, 10),
            self::profile(SpriteSet::Objects, 2, Layer::Layer1, 30),
            self::profile(SpriteSet::Objects, 3, Layer::Layer2, 20, new InteractiveObject(38, 'Wheat', 1)),
            self::profile(SpriteSet::Objects, 4, Layer::Layer2, 40),
        ]);

        foreach ($this->profiles as $profile) {
            // Sprite o4 is not exported
            if ($profile->set !== SpriteSet::Objects || $profile->id !== 4) {
                $images->save($profile->set, $profile->id, new RenderedSprite('png', 1.0, 1.0));
            }
        }

        $images->flush(SpriteSet::Grounds);
        $images->flush(SpriteSet::Objects);
    }

    public function testMostUsedFirst(): void
    {
        $selected = $this->service->select($this->profiles, new SpriteFilter());

        $this->assertSame(['g1', 'o2', 'o3', 'o1'], self::names($selected->sprites));
        $this->assertSame(1, $selected->missingImages);
    }

    public function testFilterAndLimit(): void
    {
        $this->assertSame(['o2', 'o3', 'o1'], self::names($this->service->select($this->profiles, new SpriteFilter(set: SpriteSet::Objects))->sprites));
        $this->assertSame(['o3', 'o1'], self::names($this->service->select($this->profiles, new SpriteFilter(layer: Layer::Layer2))->sprites));
        $this->assertSame(['g1', 'o2'], self::names($this->service->select($this->profiles, new SpriteFilter(), limit: 2)->sprites));
        $this->assertSame(['o1'], self::names($this->service->select($this->profiles, new SpriteFilter(SpriteSelection::parse(['o1'])))->sprites));
    }

    public function testAlreadyClassified(): void
    {
        $this->types->save(SpriteTypeResult::success(SpriteSet::Objects, 2, new SpriteType(SpriteCategory::Tree, 'tree', [], ''), 'luna'));
        $this->types->save(SpriteTypeResult::failure(SpriteSet::Objects, 3, SpriteTypeError::ClassificationFailed, 'error'));

        $all = new SpriteFilter();

        $this->assertSame(['g1', 'o1'], self::names($this->service->select($this->profiles, $all)->sprites));
        $this->assertSame(['g1', 'o3', 'o1'], self::names($this->service->select($this->profiles, $all, retryErrors: true)->sprites));
        $this->assertSame(['g1', 'o2', 'o3', 'o1'], self::names($this->service->select($this->profiles, $all, force: true)->sprites));
    }

    private static function profile(SpriteSet $set, int $id, Layer $layer, int $maps, ?InteractiveObject $interactive = null): SpriteProfile
    {
        return new SpriteProfile($set, $id, $layer, $maps, $maps, 0, [], [], [], [], [], $interactive);
    }

    /**
     * @param list<SpriteProfile> $profiles
     * @return list<string>
     */
    private static function names(array $profiles): array
    {
        return array_map(static fn (SpriteProfile $profile) => $profile->set->spriteName($profile->id), $profiles);
    }
}
