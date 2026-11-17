<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Application;

use GdImage;
use MapClassifier\Application\Config;
use MapClassifier\Application\Kernel;
use MapClassifier\Tests\ResponseFixtures;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function array_column;
use function array_diff_key;
use function array_find;
use function array_key_first;
use function array_key_last;
use function array_keys;
use function array_map;
use function array_slice;
use function array_sum;
use function copy;
use function count;
use function file_get_contents;
use function getenv;
use function getimagesize;
use function imagecolorat;
use function imagecreatefrompng;
use function imagesx;
use function imagesy;
use function is_dir;
use function json_decode;
use function mkdir;
use function preg_replace;

/**
 * Full workflow using the real Dofus client for rendering, and a fake OpenAI API
 */
final class WorkflowTest extends TestCase
{
    use TempDirTrait;

    private Application $app;
    private Config $config;

    /** @var list<array<string, mixed>> Bodies of the requests sent to the fake OpenAI API */
    private array $requests = [];

    protected function setUp(): void
    {
        // DOFUS_PATH, or the client downloaded by "bin/console client:download" (var/dofus)
        $dofusPath = array_find(
            [getenv('DOFUS_PATH') ?: '/dofus', __DIR__ . '/../../var/dofus'],
            static fn (string $path) => is_dir($path . '/data/maps'),
        ) ?? $this->markTestSkipped('Dofus client not available: set DOFUS_PATH, or run "bin/console client:download"');

        $this->config = new Config($dofusPath, $this->tempDir(), 'sk-test', openAiModel: 'luna', langDir: __DIR__ . '/../Fixtures/lang');
        mkdir($this->config->keysDir, 0755, true);
        copy(__DIR__ . '/../Fixtures/keys/10000_0706131721.txt', $this->config->keysDir . '/10000_0706131721.txt');

        $this->openAi(ResponseFixtures::success(ResponseFixtures::classification('snow')));
    }

    public function testWorkflow(): void
    {
        // Render: 10000 has a key, 10001 has not
        $render = $this->exec('maps:render', ['ids' => ['10000-10001']]);
        $this->assertStringContainsString('1 maps rendered, 1 failed', $render);
        $size = getimagesize($this->config->rendersDir . '/10000.png');
        $this->assertIsArray($size);
        $this->assertSame([742, 432], [$size[0], $size[1]]);
        $this->assertSame('missing_key', $this->mapResult(10001)['error']);

        // Already rendered or failed maps are skipped
        $this->assertStringContainsString('Nothing to render', $this->exec('maps:render', ['ids' => ['10000-10001']]));

        // Classify
        $this->assertStringContainsString('1 maps classified', $this->exec('classify:run', ['-c' => '2']));

        $result = $this->mapResult(10000);
        $this->assertSame('ok', $result['status']);
        $this->assertSame('snow', $result['biome']);
        $this->assertSame('0706131721', $result['version']);

        // Already classified maps are skipped
        $this->assertStringContainsString('Nothing to classify', $this->exec('classify:run'));

        // Export, in all formats by default
        $this->assertStringContainsString('2 maps exported (1 in error)', $this->exec('export'));
        $this->assertFileExists($this->config->varDir . '/report.html');
        $export = json_decode((string) file_get_contents($this->config->varDir . '/maps.json'), true);
        $this->assertSame(['10000', '10001'], array_map('strval', array_keys($export)));
        $this->assertSame('snow', $export[10000]['biome']);
        $this->assertSame(['id', 'version', 'location', 'status'], array_slice(array_keys($export[10000]), 0, 4));
        $this->assertSame(['id' => 320, 'name' => 'Kwismas Island', 'biome' => 'snow'], $export[10000]['location']['sub_area']);

        $subAreas = json_decode((string) file_get_contents($this->config->varDir . '/sub_areas.json'), true);
        $this->assertSame([
            'id' => 320,
            'name' => 'Kwismas Island',
            'area' => ['id' => 42, 'name' => 'Arctic Area'],
            'biome' => 'snow',
            'votes' => ['snow' => 1],
            'outdoor_only' => true,
            'total_maps' => $subAreas[320]['total_maps'],
            'classified_maps' => 1,
        ], $subAreas[320]);
        $this->assertNull($subAreas[95]['biome']);
        $this->assertSame('Arctic Area', $export[10000]['location']['area']['name']);

        $this->assertStringContainsString('1 maps exported (1 in error)', $this->exec('export', ['--only-ok' => true]));
        $this->assertCount(1, json_decode((string) file_get_contents($this->config->varDir . '/maps.json'), true));

        // Filters and a single format, with a custom output
        $selected = $this->exec('export', ['ids' => ['10001'], '--format' => ['json'], '--output' => $this->config->varDir . '/selected.json']);
        $this->assertStringContainsString('1 maps exported (1 in error)', $selected);
        $this->assertSame(['10001'], array_map('strval', array_keys(json_decode((string) file_get_contents($this->config->varDir . '/selected.json'), true))));

        // HTML gallery
        $this->assertStringContainsString('1 maps exported', $this->exec('export', ['--format' => ['html'], '--biome' => 'snow']));
        $html = (string) file_get_contents($this->config->varDir . '/report.html');
        $this->assertStringContainsString('src="renders/10000.png"', $html);
        $this->assertStringContainsString('A forest crossed by a river.', $html);
        $this->assertStringContainsString('data-status="ok" data-biome="snow" data-location="wild" data-indoor="outdoor" data-area="Arctic Area" data-search="10000 arctic area kwismas island  trees river a forest crossed by a river. "', $html);
        $this->assertStringContainsString('<option value="lake_river">lake_river</option>', $html);
        $this->assertStringContainsString('<option value="dungeon">dungeon</option>', $html);
        $this->assertStringContainsString('<option value="Arctic Area">Arctic Area</option>', $html);
        $this->assertStringContainsString('Arctic Area › Kwismas Island <small>(sub area: snow)</small>', $html);
    }

    public function testRetryErrors(): void
    {
        $this->exec('maps:render', ['ids' => ['10000']]);
        $this->openAi(['error' => ['message' => 'Server error']], 500);
        $this->assertStringContainsString('0 maps classified, 1 errors', $this->exec('classify:run'));
        $this->assertSame('classification_failed', $this->mapResult(10000)['error']);
        $this->assertSame('HTTP 500: Server error', $this->mapResult(10000)['error_message']);

        $this->openAi(ResponseFixtures::success(ResponseFixtures::classification('snow')));
        $this->assertStringContainsString('Nothing to classify', $this->exec('classify:run'));
        $this->assertStringContainsString('1 maps classified', $this->exec('classify:run', ['--retry-errors' => true]));
        $this->assertSame('snow', $this->mapResult(10000)['biome']);
    }

    public function testLocationFilter(): void
    {
        // 10000 is in sub area 320 (Kwismas Island), area 42, super area 0
        $this->assertStringContainsString('Nothing to render', $this->exec('maps:render', ['ids' => ['10000'], '--super-area' => '3']));
        $this->assertStringContainsString('Nothing to render', $this->exec('maps:render', ['ids' => ['10000'], '--sub-area' => '95']));
        $this->assertStringContainsString('1 maps rendered', $this->exec('maps:render', ['ids' => ['10000-10001'], '--super-area' => '0', '--area' => '42', '--sub-area' => '320', '--limit' => '1']));

        $this->assertStringContainsString('Nothing to classify', $this->exec('classify:run', ['--area' => '18']));
        $this->assertStringContainsString('1 maps classified', $this->exec('classify:run', ['--area' => '42']));
    }

    public function testSpritesAnalyze(): void
    {
        $this->exec('maps:render', ['ids' => ['10000']]);
        $this->exec('classify:run');

        $output = $this->exec('sprites:analyze', ['ids' => ['10000-10001']]);
        $this->assertStringContainsString('1 maps skipped: missing_key', $output);
        $this->assertMatchesRegularExpression('/1 maps analyzed: \d+ ground sprites and \d+ object sprites/', $output);

        $objects = json_decode((string) file_get_contents($this->config->varDir . '/sprite_profiles.json'), true);
        $this->assertSame(['grounds', 'objects'], array_keys($objects));
        $this->assertFileDoesNotExist($this->config->varDir . '/sprites.json', 'Only written by the export');
        $this->assertNotEmpty($objects['objects']);

        foreach ([...$objects['grounds'], ...$objects['objects']] as $profile) {
            $this->assertSame(1, $profile['maps']);
            $this->assertSame(1, $profile['classified_maps']);
            $usage = ['maps' => 1, 'occurrences' => $profile['occurrences']];
            $this->assertSame(['snow' => $usage], $profile['biomes']);
            $this->assertSame(['outdoor' => $usage], $profile['indoor']);
            $this->assertSame([['id' => 320, 'name' => 'Kwismas Island'] + $usage], $profile['sub_areas']);
            $this->assertContains($profile['layer'], ['background', 'ground', 'layer1', 'layer2']);
            $this->assertArrayNotHasKey('super_areas', $profile);
            $this->assertArrayHasKey('interactive', $profile);
            // A sprite only used as background has no cell, so no movement
            $this->assertEqualsWithDelta($profile['layer'] === 'background' ? 0.0 : 1.0, array_sum($profile['movements']), 0.001, 'Movement frequencies');
            // Neighbors on the cell layers, with one entry per orientation of the sprite on its main layer
            if ($profile['layer'] !== 'background') {
                $this->assertSame(10000, $profile['example']['map'], 'Example on the only analyzed map');
                $this->assertNotEmpty($profile['neighbors']);
                $this->assertLessThanOrEqual($profile['occurrences'], array_sum(array_column($profile['neighbors'], 'cells')));
            } else {
                $this->assertNull($profile['example']);
                $this->assertSame([], $profile['neighbors']);
            }
        }

        // Export used sprites to SVG
        $sprites = $this->exec('sprites:images');
        $this->assertMatchesRegularExpression('/grounds: \d+ exported, 0 already exported, 0 failed/', $sprites);
        $this->assertMatchesRegularExpression('/objects: \d+ exported, 0 already exported, 0 failed/', $sprites);

        foreach (['grounds', 'objects'] as $set) {
            foreach ($objects[$set] as $id => $_) {
                $this->assertFileExists($this->config->varDir . '/sprites/' . $set . '/' . $id . '.png');
            }

            $metadata = json_decode((string) file_get_contents($this->config->varDir . '/sprites/' . $set . '/metadata.json'), true);
            $this->assertSameSize($objects[$set], $metadata);
        }

        $size = getimagesize($this->config->varDir . '/sprites/objects/' . array_key_first($objects['objects']) . '.png');
        $this->assertIsArray($size);
        $this->assertSame('image/png', $size['mime']);
        $this->assertMatchesRegularExpression('/objects: 0 exported, \d+ already exported/', $this->exec('sprites:images'));

        // Example maps, with a magenta frame around the sprite
        $firstObject = array_key_first($objects['objects']);
        $this->exec('maps:render', ['ids' => ['10000']]);
        $examples = $this->exec('sprites:examples');
        $this->assertMatchesRegularExpression('/\d+ examples rendered, 0 already rendered, 0 failed/', $examples);
        $this->assertMatchesRegularExpression('/0 examples rendered, \d+ already rendered/', $this->exec('sprites:examples'));
        $example = $this->config->spriteExamplesDir . '/objects/' . $firstObject . '-10000-' . $objects['objects'][$firstObject]['example']['cell'] . '.png';
        $this->assertFileExists($example);
        $image = imagecreatefrompng($example);
        $this->assertNotFalse($image);
        $this->assertSame([742, 432], [imagesx($image), imagesy($image)]);
        $this->assertTrue(self::hasMagenta($image), 'Frame drawn');
        $this->assertFalse(self::hasMagenta(imagecreatefrompng($this->config->rendersDir . '/10000.png') ?: throw new RuntimeException()), 'No magenta on the map');

        // Export: JSON and HTML gallery
        $count = count($objects['grounds']) + count($objects['objects']);
        $this->assertStringContainsString($count . ' sprites exported (0 with a type)', $this->exec('sprites:export'));
        $this->assertFileExists($this->config->varDir . '/sprites.json');
        $html = (string) file_get_contents($this->config->varDir . '/sprites.html');
        $this->assertStringContainsString('<option value="Arctic Area">Arctic Area</option>', $html);
        $this->assertStringContainsString('"sub_areas":[{"id":320,"name":"Kwismas Island","maps":1,"occurrences":', $html);
        $this->assertStringContainsString('&quot;sprites&quot;', $html);

        // Classify sprite types
        $this->openAi(ResponseFixtures::success([
            'category' => 'tree',
            'name' => 'snowy pine',
            'keywords' => ['pine', 'snow'],
            'description' => 'A snowy pine.',
            'transition' => false,
            'usage' => 'big_object',
            'orientation' => null,
        ]));

        $sent = count($this->requests);
        $this->assertStringContainsString('1 sprites classified', $this->exec('sprites:classify', ['ids' => ['o' . $firstObject]]));
        $this->assertCount($sent + 1, $this->requests);
        $content = $this->requests[$sent]['input'][0]['content'];
        $this->assertSame(['input_text', 'input_image', 'input_image'], array_column($content, 'type'), 'Sprite and example map');
        $this->assertStringStartsWith('data:image/png;base64,', $content[1]['image_url']);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $content[2]['image_url']);
        $this->assertStringEndsWith('framed in magenta.', $content[0]['text']);
        $this->assertStringContainsString('Nothing to classify', $this->exec('sprites:classify', ['ids' => ['o' . $firstObject]]));
        $this->assertStringContainsString('1 sprites classified', $this->exec('sprites:classify', ['ids' => ['o' . $firstObject], '--force' => true]));
        $this->assertStringContainsString('2 sprites classified', $this->exec('sprites:classify', ['--set' => 'grounds', '--limit' => '2']));

        // Types are exported to sprites.json by sprites:classify
        $joined = json_decode((string) file_get_contents($this->config->varDir . '/sprites.json'), true);
        $this->assertSame('snowy pine', $joined['objects'][$firstObject]['type']['name']);
        $this->assertSame(array_keys($objects['objects']), array_keys($joined['objects']));
        $this->assertSame($objects['objects'][$firstObject], array_diff_key($joined['objects'][$firstObject], ['image' => 0, 'example_image' => 0, 'type' => 0]), 'Profile, with image and type');
        $this->assertStringStartsWith('objects/' . $firstObject . '-10000-', $joined['objects'][$firstObject]['example_image']);
        $this->assertSame('objects/' . $firstObject . '.png', $joined['objects'][$firstObject]['image']['image']);

        // Type is displayed in the HTML gallery, also exported by sprites:classify
        $this->assertStringContainsString('"type":{"category":"tree","name":"snowy pine","keywords":["pine","snow"],"description":"A snowy pine.","transition":false,"usage":"big_object","orientation":null}', (string) file_get_contents($this->config->varDir . '/sprites.html'));
        $this->assertStringContainsString('<option value="tree">tree</option>', (string) file_get_contents($this->config->varDir . '/sprites.html'));

        // Filtered export
        $filtered = $this->exec('sprites:export', ['ids' => ['o' . $firstObject], '--format' => ['json'], '--output' => $this->config->varDir . '/filtered.json']);
        $this->assertStringContainsString('1 sprites exported (1 with a type)', $filtered);
        $this->assertSame([], json_decode((string) file_get_contents($this->config->varDir . '/filtered.json'), true)['grounds']);

        // Export again after a new analyze: stored types are joined
        $this->exec('sprites:analyze', ['ids' => ['10000-10001']]);
        $this->assertStringContainsString($count . ' sprites exported (3 with a type)', $this->exec('sprites:export', ['--format' => ['json']]));
        $objects = json_decode((string) file_get_contents($this->config->varDir . '/sprites.json'), true);
        $this->assertSame(['category' => 'tree', 'name' => 'snowy pine', 'keywords' => ['pine', 'snow'], 'description' => 'A snowy pine.', 'transition' => false, 'usage' => 'big_object', 'orientation' => null], $objects['objects'][$firstObject]['type']);
        $this->assertArrayHasKey('type', $objects['objects'][array_key_last($objects['objects'])]);
        $this->assertNull($objects['objects'][array_key_last($objects['objects'])]['type']);
    }

    public function testExportFormatOptions(): void
    {
        $tester = new CommandTester($this->app->find('export'));

        $this->assertSame(Command::INVALID, $tester->execute(['--format' => ['xml']]));
        $this->assertStringContainsString('Unknown format "xml", available: json, html', $tester->getDisplay());

        $this->assertSame(Command::INVALID, $tester->execute(['--output' => 'foo.json']));
        $this->assertStringContainsString('The --output option requires a single --format', $tester->getDisplay());

        $tester = new CommandTester($this->app->find('sprites:export'));
        $this->assertSame(Command::INVALID, $tester->execute(['--format' => ['json', 'html'], '--output' => 'foo.json']));

        $tester = new CommandTester($this->app->find('sprites:classify'));
        $this->assertSame(Command::INVALID, $tester->execute(['ids' => ['x12']]));
        $this->assertStringContainsString('Invalid sprite id or range "x12"', $tester->getDisplay());
        $this->assertSame(Command::INVALID, $tester->execute(['--layer' => 'layer3']));

        $tester = new CommandTester($this->app->find('export'));
        $this->assertSame(Command::INVALID, $tester->execute(['--biome' => 'ocean']));
        $this->assertStringContainsString('Invalid biome "ocean"', $tester->getDisplay());
    }

    public function testSpritesCommandsWithoutAnalyze(): void
    {
        foreach (['sprites:images', 'sprites:classify', 'sprites:export'] as $command) {
            $tester = new CommandTester($this->app->find($command));
            $this->assertSame(Command::FAILURE, $tester->execute([]), $command);
            $this->assertStringContainsString('sprite_profiles.json not found: run sprites:analyze', (string) preg_replace('/\s+/', ' ', $tester->getDisplay()), $command);
        }
    }

    /**
     * Use a fake OpenAI API, answering all requests with this response
     *
     * @param array<string, mixed> $body
     */
    private function openAi(array $body, int $status = 200): void
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($body, $status): ResponseInterface {
            $this->requests[] = (array) json_decode((string) ($options['body'] ?? ''), true);

            return new JsonMockResponse($body, ['http_code' => $status]);
        });
        $this->app = (new Kernel($this->config, $http))->application();
    }

    private static function hasMagenta(GdImage $image): bool
    {
        for ($x = 0; $x < imagesx($image); ++$x) {
            for ($y = 0; $y < imagesy($image); ++$y) {
                if ((imagecolorat($image, $x, $y) & 0xFFFFFF) === 0xFF00FF) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function exec(string $command, array $input = []): string
    {
        $tester = new CommandTester($this->app->find($command));
        $tester->setInputs(['yes']);
        $tester->execute($input);
        $tester->assertCommandIsSuccessful();

        return $tester->getDisplay();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapResult(int $id): array
    {
        return json_decode((string) file_get_contents($this->config->resultsDir . '/' . $id . '.json'), true);
    }
}
