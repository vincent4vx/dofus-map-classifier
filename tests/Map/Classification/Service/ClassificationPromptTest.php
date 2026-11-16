<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Map\Classification\Service;

use MapClassifier\Map\Classification\Model\Biome;
use MapClassifier\Map\Classification\Model\LocationType;
use MapClassifier\Map\Classification\Service\ClassificationPrompt;
use PHPUnit\Framework\TestCase;

use function array_column;

final class ClassificationPromptTest extends TestCase
{
    public function testRequestBody(): void
    {
        $body = (new ClassificationPrompt('luna'))->requestBody(42, 'data:image/jpeg;base64,xxx');

        $this->assertSame('luna', $body['model']);
        $this->assertSame(ClassificationPrompt::instructions(), $body['instructions']);
        $this->assertSame([
            ['type' => 'input_text', 'text' => 'Classify the map 42.'],
            ['type' => 'input_image', 'image_url' => 'data:image/jpeg;base64,xxx', 'detail' => 'high'],
        ], $body['input'][0]['content']);
        $this->assertSame('json_schema', $body['text']['format']['type']);
        $this->assertTrue($body['text']['format']['strict']);
        $this->assertSame(ClassificationPrompt::schema(), $body['text']['format']['schema']);
        $this->assertArrayNotHasKey('reasoning', $body);
    }

    public function testRequestBodyWithReasoning(): void
    {
        $body = (new ClassificationPrompt('luna', 'low'))->requestBody(42, 'url');

        $this->assertSame(['effort' => 'low'], $body['reasoning']);
    }

    public function testSchemaMatchesEnums(): void
    {
        $schema = ClassificationPrompt::schema();

        $this->assertSame([
            ['type' => 'string', 'enum' => array_column(Biome::cases(), 'value')],
            ['type' => 'null'],
        ], $schema['properties']['biome']['anyOf']);
        $this->assertSame(array_column(LocationType::cases(), 'value'), $schema['properties']['location_type']['enum']);
        $this->assertSame(array_keys($schema['properties']), $schema['required']);
        $this->assertFalse($schema['additionalProperties']);
    }

    public function testInstructionsListAllValues(): void
    {
        $instructions = ClassificationPrompt::instructions();

        foreach ([...Biome::cases(), ...LocationType::cases()] as $case) {
            $this->assertStringContainsString('- ' . $case->value . ': ' . $case->description(), $instructions);
        }
    }
}
