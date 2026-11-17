<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Classification\Service;

use MapClassifier\Sprite\Classification\Model\SpriteType;
use MapClassifier\Sprite\Classification\Model\SpriteTypeError;
use MapClassifier\Sprite\Classification\Model\SpriteTypeResult;
use MapClassifier\Sprite\Classification\Repository\SpriteTypeRepository;
use MapClassifier\Sprite\Classification\Service\SpriteTypeResolver;
use MapClassifier\Sprite\Model\InteractiveObject;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\SpriteProfile;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;

final class SpriteTypeResolverTest extends TestCase
{
    use TempDirTrait;

    public function testInteractiveNameTakesPriority(): void
    {
        $repository = new SpriteTypeRepository($this->tempDir());
        $type = SpriteType::fromArray(['category' => 'crop', 'name' => 'wheat stalks', 'keywords' => ['cereal'], 'description' => '']);
        $repository->save(SpriteTypeResult::success(SpriteSet::Objects, 7511, $type, 'luna'));
        $repository->save(SpriteTypeResult::success(SpriteSet::Objects, 12, $type, 'luna'));
        $repository->save(SpriteTypeResult::failure(SpriteSet::Objects, 13, SpriteTypeError::ClassificationFailed, 'error'));

        $resolver = new SpriteTypeResolver($repository);
        $wheat = self::profile(7511, new InteractiveObject(38, 'Wheat', 1));

        $wheatType = $resolver->type($wheat);
        $this->assertNotNull($wheatType);
        $this->assertSame('wheat', $wheatType->name);
        $this->assertSame(['cereal', 'wheat stalks', 'resource'], $wheatType->keywords);
        $this->assertEquals($type, $resolver->type(self::profile(12)));
        $this->assertNull($resolver->type(self::profile(13)));
        $this->assertNull($resolver->type(self::profile(14, new InteractiveObject(38, 'Wheat', 1))));
        $this->assertSame(['category' => 'crop', 'name' => 'wheat', 'keywords' => ['cereal', 'wheat stalks', 'resource'], 'description' => '', 'transition' => null, 'usage' => null, 'orientation' => null], $wheatType->jsonSerialize());
    }

    private static function profile(int $id, ?InteractiveObject $interactive = null): SpriteProfile
    {
        return new SpriteProfile(SpriteSet::Objects, $id, Layer::Layer2, 1, 1, 0, [], [], [], [], [], $interactive);
    }
}
