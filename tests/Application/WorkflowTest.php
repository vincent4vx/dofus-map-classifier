<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Application;

use MapClassifier\Application\Config;
use MapClassifier\Application\Kernel;
use MapClassifier\Tests\BatchFixtures;
use MapClassifier\Tests\FakeBatchApi;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

use function array_find;
use function array_key_first;
use function array_key_last;
use function array_keys;
use function array_map;
use function array_slice;
use function copy;
use function count;
use function file_get_contents;
use function getenv;
use function getimagesize;
use function is_dir;
use function json_decode;
use function json_encode;
use function mkdir;
use function preg_replace;

/**
 * Full workflow using the real Dofus client for rendering, and a fake OpenAI API
 */
final class WorkflowTest extends TestCase
{
    use TempDirTrait;

    private FakeBatchApi $api;
    private Application $app;
    private Config $config;

    protected function setUp(): void
    {
        // DOFUS_PATH, or the client downloaded by "bin/console client:download" (var/dofus)
        $dofusPath = array_find(
            [getenv('DOFUS_PATH') ?: '/dofus', __DIR__ . '/../../var/dofus'],
            static fn (string $path) => is_dir($path . '/data/maps'),
        ) ?? $this->markTestSkipped('Dofus client not available: set DOFUS_PATH, or run "bin/console client:download"');

        $this->config = new Config($dofusPath, $this->tempDir(), openAiModel: 'luna', langDir: __DIR__ . '/../Fixtures/lang');
        mkdir($this->config->keysDir, 0755, true);
        copy(__DIR__ . '/../Fixtures/keys/10000_0706131721.txt', $this->config->keysDir . '/10000_0706131721.txt');

        $this->api = new FakeBatchApi();
        $this->app = (new Kernel($this->config, $this->api))->application();
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

        // Submit
        $submit = $this->exec('classify:submit');
        $this->assertStringContainsString('submitted as batch_1', $submit);
        $this->assertCount(1, $this->api->batches);
        $this->assertSame('/v1/responses', $this->api->metadata['batch_1']['endpoint']);
        $this->assertStringContainsString('"custom_id":"map-10000"', $this->api->files['file-1']);

        // Map in a pending batch is not submitted again
        $this->assertStringContainsString('Nothing to submit', $this->exec('classify:submit'));

        // Status
        $this->assertStringContainsString('validating', $this->exec('classify:status'));
        $this->assertStringContainsString('0 batch(es) imported, 1 still running', $this->exec('classify:import'));

        // Import
        $this->api->complete('batch_1', BatchFixtures::successLine(10000, BatchFixtures::classification('snow')) . "\n");
        $this->assertStringContainsString('1 classified, 0 errors, 0 without result', $this->exec('classify:import'));

        $result = $this->mapResult(10000);
        $this->assertSame('ok', $result['status']);
        $this->assertSame('snow', $result['biome']);
        $this->assertSame('0706131721', $result['version']);

        // Already imported batches are ignored
        $this->assertStringContainsString('0 batch(es) imported, 0 still running', $this->exec('classify:import'));
        $this->assertStringContainsString('No batch to display', $this->exec('classify:status'));
        $this->assertMatchesRegularExpression('/batch_1\s+completed\s+1\s+2\/2/', $this->exec('classify:status', ['--all' => true]));
        $this->assertStringContainsString('Nothing to submit', $this->exec('classify:submit'));

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

    public function testResubmitErrors(): void
    {
        $this->exec('maps:render', ['ids' => ['10000']]);
        $this->exec('classify:submit');
        $this->api->complete('batch_1', BatchFixtures::responseLine(10000, 500, ['error' => ['message' => 'Server error']]) . "\n");
        $this->assertStringContainsString('0 classified, 1 errors', $this->exec('classify:import'));
        $this->assertSame('classification_failed', $this->mapResult(10000)['error']);

        $this->assertStringContainsString('Nothing to submit', $this->exec('classify:submit'));
        $this->assertStringContainsString('submitted as batch_2', $this->exec('classify:submit', ['--retry-errors' => true]));
    }

    public function testMissingResultsAreSubmittedAgain(): void
    {
        $this->exec('maps:render', ['ids' => ['10000']]);
        $this->exec('classify:submit');
        $this->api->complete('batch_1', '');

        $this->assertStringContainsString('0 classified, 0 errors, 1 without result', $this->exec('classify:import'));
        $this->assertStringContainsString('submitted as batch_2', $this->exec('classify:submit'));
    }

    public function testSyncRun(): void
    {
        $http = new MockHttpClient(static fn () => new JsonMockResponse(
            json_decode(BatchFixtures::successLine(10000, BatchFixtures::classification('snow')), true)['response']['body'],
        ));
        $this->app = (new Kernel(new Config($this->config->dofusPath, $this->config->varDir, 'sk-test', langDir: $this->config->langDir), $this->api, $http))->application();

        $this->exec('maps:render', ['ids' => ['10000']]);
        $this->exec('classify:submit');

        // Map in a pending batch
        $this->assertStringContainsString('Nothing to classify', $this->exec('classify:run'));

        $run = $this->exec('classify:run', ['--include-pending' => true, '-c' => '2']);
        $this->assertStringContainsString('1 maps classified', $run);
        $this->assertSame('snow', $this->mapResult(10000)['biome']);

        $this->assertStringContainsString('Nothing to classify', $this->exec('classify:run', ['--include-pending' => true]));
    }

    public function testLocationFilter(): void
    {
        // 10000 is in sub area 320 (Kwismas Island), area 42, super area 0
        $this->assertStringContainsString('Nothing to render', $this->exec('maps:render', ['ids' => ['10000'], '--super-area' => '3']));
        $this->assertStringContainsString('Nothing to render', $this->exec('maps:render', ['ids' => ['10000'], '--sub-area' => '95']));
        $this->assertStringContainsString('1 maps rendered', $this->exec('maps:render', ['ids' => ['10000-10001'], '--super-area' => '0', '--area' => '42', '--sub-area' => '320', '--limit' => '1']));

        $this->assertStringContainsString('Nothing to submit', $this->exec('classify:submit', ['--area' => '18']));
        $this->assertStringContainsString('submitted as batch_1', $this->exec('classify:submit', ['--area' => '42']));
    }

    public function testObjectsAnalyze(): void
    {
        $this->exec('maps:render', ['ids' => ['10000']]);
        $this->exec('classify:submit');
        $this->api->complete('batch_1', BatchFixtures::successLine(10000, BatchFixtures::classification('snow')) . "\n");
        $this->exec('classify:import');

        $output = $this->exec('objects:analyze', ['ids' => ['10000-10001']]);
        $this->assertStringContainsString('1 maps skipped: missing_key', $output);
        $this->assertMatchesRegularExpression('/1 maps analyzed: \d+ ground sprites and \d+ object sprites/', $output);

        $objects = json_decode((string) file_get_contents($this->config->varDir . '/sprite_profiles.json'), true);
        $this->assertSame(['grounds', 'objects'], array_keys($objects));
        $this->assertFileDoesNotExist($this->config->varDir . '/objects.json', 'Only written by the export');
        $this->assertNotEmpty($objects['objects']);

        foreach ([...$objects['grounds'], ...$objects['objects']] as $profile) {
            $this->assertSame(1, $profile['maps']);
            $this->assertSame(1, $profile['classified_maps']);
            $this->assertSame(['snow' => 1], $profile['biomes']);
            $this->assertSame(['outdoor' => 1], $profile['indoor']);
            $this->assertSame([['id' => 320, 'name' => 'Kwismas Island', 'maps' => 1]], $profile['sub_areas']);
            $this->assertContains($profile['layer'], ['background', 'ground', 'layer1', 'layer2']);
            $this->assertArrayNotHasKey('super_areas', $profile);
            $this->assertArrayHasKey('interactive', $profile);
        }

        // Export used sprites to SVG
        $sprites = $this->exec('objects:sprites');
        $this->assertMatchesRegularExpression('/grounds: \d+ exported, 0 already exported, 0 failed/', $sprites);
        $this->assertMatchesRegularExpression('/objects: \d+ exported, 0 already exported, 0 failed/', $sprites);

        foreach (['grounds', 'objects'] as $set) {
            foreach ($objects[$set] as $id => $_) {
                $this->assertFileExists($this->config->varDir . '/sprites/' . $set . '/' . $id . '.png');
            }

            $sizes = json_decode((string) file_get_contents($this->config->varDir . '/sprites/' . $set . '/sizes.json'), true);
            $this->assertSameSize($objects[$set], $sizes);
        }

        $size = getimagesize($this->config->varDir . '/sprites/objects/' . array_key_first($objects['objects']) . '.png');
        $this->assertIsArray($size);
        $this->assertSame('image/png', $size['mime']);
        $this->assertMatchesRegularExpression('/objects: 0 exported, \d+ already exported/', $this->exec('objects:sprites'));

        // Export: JSON and HTML gallery
        $count = count($objects['grounds']) + count($objects['objects']);
        $this->assertStringContainsString($count . ' sprites exported (0 with a type)', $this->exec('objects:export'));
        $this->assertFileExists($this->config->varDir . '/objects.json');
        $html = (string) file_get_contents($this->config->varDir . '/objects.html');
        $this->assertStringContainsString('<option value="Arctic Area">Arctic Area</option>', $html);
        $this->assertStringContainsString('["Kwismas Island",1]', $html);
        $this->assertStringContainsString('&quot;sprites&quot;', $html);

        // Classify sprite types
        $http = new MockHttpClient(static fn (string $method, string $url, array $options) => new JsonMockResponse([
            'model' => 'luna',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode([
                'category' => 'tree',
                'name' => 'snowy pine',
                'keywords' => ['pine', 'snow'],
                'description' => 'A snowy pine.',
            ])]]]],
        ]));
        $this->app = (new Kernel(new Config($this->config->dofusPath, $this->config->varDir, 'sk-test', langDir: $this->config->langDir), $this->api, $http))->application();

        $firstObject = array_key_first($objects['objects']);
        $this->assertStringContainsString('1 sprites classified', $this->exec('objects:classify', ['ids' => ['o' . $firstObject]]));
        $this->assertStringContainsString('Nothing to classify', $this->exec('objects:classify', ['ids' => ['o' . $firstObject]]));
        $this->assertStringContainsString('1 sprites classified', $this->exec('objects:classify', ['ids' => ['o' . $firstObject], '--force' => true]));
        $this->assertStringContainsString('2 sprites classified', $this->exec('objects:classify', ['--set' => 'grounds', '--limit' => '2']));

        // Types are exported to objects.json by objects:classify
        $joined = json_decode((string) file_get_contents($this->config->varDir . '/objects.json'), true);
        $this->assertSame('snowy pine', $joined['objects'][$firstObject]['type']['name']);
        $this->assertSame(array_keys($objects['objects']), array_keys($joined['objects']));
        $this->assertSame($objects['objects'][$firstObject] + ['type' => $joined['objects'][$firstObject]['type']], $joined['objects'][$firstObject]);

        // Type is displayed in the HTML gallery, also exported by objects:classify
        $this->assertStringContainsString('["tree","snowy pine",["pine","snow"],"A snowy pine."]', (string) file_get_contents($this->config->varDir . '/objects.html'));
        $this->assertStringContainsString('<option value="tree">tree</option>', (string) file_get_contents($this->config->varDir . '/objects.html'));

        // Export again after a new analyze: stored types are joined
        $this->exec('objects:analyze', ['ids' => ['10000-10001']]);
        $this->assertStringContainsString($count . ' sprites exported (3 with a type)', $this->exec('objects:export', ['--format' => ['json']]));
        $objects = json_decode((string) file_get_contents($this->config->varDir . '/objects.json'), true);
        $this->assertSame(['category' => 'tree', 'name' => 'snowy pine', 'keywords' => ['pine', 'snow'], 'description' => 'A snowy pine.'], $objects['objects'][$firstObject]['type']);
        $this->assertArrayHasKey('type', $objects['objects'][array_key_last($objects['objects'])]);
        $this->assertNull($objects['objects'][array_key_last($objects['objects'])]['type']);
    }

    public function testDiscard(): void
    {
        $this->exec('maps:render', ['ids' => ['10000']]);
        $this->exec('classify:submit');
        $this->api->complete('batch_1', BatchFixtures::successLine(10000, BatchFixtures::classification('snow')) . "\n");

        $this->assertStringContainsString('batch_1 discarded (1 maps)', $this->exec('classify:discard', ['batches' => ['batch_1']]));
        $this->assertMatchesRegularExpression('/batch_1.*\(discarded\)/', $this->exec('classify:status', ['--all' => true]));

        // Results are never imported, and the map can be submitted again
        $this->assertStringContainsString('0 batch(es) imported, 0 still running', $this->exec('classify:import'));
        $this->assertFileDoesNotExist($this->config->resultsDir . '/10000.json');
        $this->assertStringContainsString('submitted as batch_2', $this->exec('classify:submit'));

        $tester = new CommandTester($this->app->find('classify:discard'));
        $tester->execute(['batches' => ['batch_1', 'batch_unknown']]);
        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('already discarded', $tester->getDisplay());
        $this->assertStringContainsString('batch_unknown not found', $tester->getDisplay());
    }

    public function testDryRun(): void
    {
        $this->exec('maps:render', ['ids' => ['10000']]);

        $this->assertStringContainsString('Dry run', $this->exec('classify:submit', ['--dry-run' => true]));
        $this->assertSame([], $this->api->batches);
    }

    public function testExportFormatOptions(): void
    {
        $tester = new CommandTester($this->app->find('export'));

        $this->assertSame(Command::INVALID, $tester->execute(['--format' => ['xml']]));
        $this->assertStringContainsString('Unknown format "xml", available: json, html', $tester->getDisplay());

        $this->assertSame(Command::INVALID, $tester->execute(['--output' => 'foo.json']));
        $this->assertStringContainsString('The --output option requires a single --format', $tester->getDisplay());

        $tester = new CommandTester($this->app->find('objects:export'));
        $this->assertSame(Command::INVALID, $tester->execute(['--format' => ['json', 'html'], '--output' => 'foo.json']));
    }

    public function testObjectsCommandsWithoutAnalyze(): void
    {
        foreach (['objects:sprites', 'objects:classify', 'objects:export'] as $command) {
            $tester = new CommandTester($this->app->find($command));
            $this->assertSame(Command::FAILURE, $tester->execute([]), $command);
            $this->assertStringContainsString('sprite_profiles.json not found: run objects:analyze', (string) preg_replace('/\s+/', ' ', $tester->getDisplay()), $command);
        }
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
