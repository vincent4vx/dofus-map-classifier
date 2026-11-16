<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Sprite\Classification\Service;

use MapClassifier\Sprite\Classification\Model\ObjectTypeClassification;
use MapClassifier\Sprite\Classification\Model\ObjectTypeResult;
use MapClassifier\Sprite\Classification\Repository\ObjectTypeRepository;
use MapClassifier\Sprite\Classification\Service\ObjectTypeResolver;
use MapClassifier\Sprite\Model\InteractiveObject;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\ObjectProfile;
use MapClassifier\Sprite\Model\SpriteSet;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;

final class ObjectTypeResolverTest extends TestCase
{
    use TempDirTrait;

    public function testInteractiveNameTakesPriority(): void
    {
        $repository = new ObjectTypeRepository($this->tempDir());
        $type = ObjectTypeClassification::fromArray(['category' => 'crop', 'name' => 'wheat stalks', 'keywords' => ['cereal'], 'description' => '']);
        $repository->save(ObjectTypeResult::success(SpriteSet::Objects, 7511, $type, 'luna'));
        $repository->save(ObjectTypeResult::success(SpriteSet::Objects, 12, $type, 'luna'));
        $repository->save(ObjectTypeResult::failure(SpriteSet::Objects, 13, 'error'));

        $resolver = new ObjectTypeResolver($repository);
        $wheat = self::profile(7511, new InteractiveObject(38, 'Wheat', 1));

        $wheatType = $resolver->type($wheat);
        $this->assertNotNull($wheatType);
        $this->assertSame('wheat', $wheatType->name);
        $this->assertSame(['cereal', 'wheat stalks', 'resource'], $wheatType->keywords);
        $this->assertEquals($type, $resolver->type(self::profile(12)));
        $this->assertNull($resolver->type(self::profile(13)));
        $this->assertNull($resolver->type(self::profile(14, new InteractiveObject(38, 'Wheat', 1))));
        $this->assertSame(['type' => ['category' => 'crop', 'name' => 'wheat', 'keywords' => ['cereal', 'wheat stalks', 'resource'], 'description' => '']], $resolver->fields($wheat));
        $this->assertSame(['type' => null], $resolver->fields(self::profile(13)));
    }

    private static function profile(int $id, ?InteractiveObject $interactive = null): ObjectProfile
    {
        return new ObjectProfile(SpriteSet::Objects, $id, Layer::Layer2, 1, 1, 0, [], [], [], [], [], $interactive);
    }
}
