<?php

declare(strict_types=1);

namespace MapClassifier\Tests\Command;

use MapClassifier\Config;
use MapClassifier\Kernel;
use MapClassifier\Tests\BatchFixtures;
use MapClassifier\Tests\FakeBatchApi;
use MapClassifier\Tests\TempDirTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

use function array_keys;
use function array_map;
use function array_slice;
use function copy;
use function file_get_contents;
use function getenv;
use function getimagesize;
use function is_dir;
use function json_decode;
use function mkdir;

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
        $dofusPath = getenv('DOFUS_PATH') ?: '/dofus';

        if (!is_dir($dofusPath . '/data/maps')) {
            $this->markTestSkipped('Dofus client not available');
        }

        $this->config = new Config($dofusPath, $this->tempDir(), openAiModel: 'luna');
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
        $this->assertSame([742, 432], array_slice(getimagesize($this->config->rendersDir . '/10000.png'), 0, 2));
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

        // Export
        $this->assertStringContainsString('2 maps exported', $this->exec('export'));
        $export = json_decode((string) file_get_contents($this->config->varDir . '/maps.json'), true);
        $this->assertSame(['10000', '10001'], array_map('strval', array_keys($export)));
        $this->assertSame('snow', $export[10000]['biome']);

        $this->assertStringContainsString('1 maps exported', $this->exec('export', ['--only-ok' => true]));

        // Report
        $this->assertStringContainsString('Report of 1 maps', $this->exec('report', ['--biome' => 'snow']));
        $html = (string) file_get_contents($this->config->varDir . '/report.html');
        $this->assertStringContainsString('src="renders/10000.png"', $html);
        $this->assertStringContainsString('A forest crossed by a river.', $html);
        $this->assertStringContainsString('data-status="ok" data-biome="snow" data-location="wild" data-indoor="outdoor" data-search="10000  trees river a forest crossed by a river. "', $html);
        $this->assertStringContainsString('<option value="lake_river">lake_river</option>', $html);
        $this->assertStringContainsString('<option value="dungeon">dungeon</option>', $html);
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
        $this->app = (new Kernel(new Config($this->config->dofusPath, $this->config->varDir, 'sk-test'), $this->api, $http))->application();

        $this->exec('maps:render', ['ids' => ['10000']]);
        $this->exec('classify:submit');

        // Map in a pending batch
        $this->assertStringContainsString('Nothing to classify', $this->exec('classify:run'));

        $run = $this->exec('classify:run', ['--include-pending' => true, '-c' => '2']);
        $this->assertStringContainsString('1 maps classified', $run);
        $this->assertSame('snow', $this->mapResult(10000)['biome']);

        $this->assertStringContainsString('Nothing to classify', $this->exec('classify:run', ['--include-pending' => true]));
    }

    public function testDryRun(): void
    {
        $this->exec('maps:render', ['ids' => ['10000']]);

        $this->assertStringContainsString('Dry run', $this->exec('classify:submit', ['--dry-run' => true]));
        $this->assertSame([], $this->api->batches);
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
