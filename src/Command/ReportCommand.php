<?php

declare(strict_types=1);

namespace MapClassifier\Command;

use BackedEnum;
use MapClassifier\Classification\Biome;
use MapClassifier\Classification\LocationType;
use MapClassifier\Config;
use MapClassifier\Lang\MapLocation;
use MapClassifier\Lang\MapLocationRepository;
use MapClassifier\Storage\MapResult;
use MapClassifier\Storage\ResultStorage;
use MapClassifier\SubArea\SubAreaBiome;
use MapClassifier\SubArea\SubAreaBiomeResolver;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_filter;
use function array_keys;
use function array_map;
use function count;
use function dirname;
use function file_put_contents;
use function htmlspecialchars;
use function implode;
use function ksort;
use function mb_strtolower;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Generate an HTML page displaying renders and their classification, for visual validation
 */
#[AsCommand('report', 'Generate an HTML gallery of classified maps for visual validation')]
final class ReportCommand extends Command
{
    public function __construct(
        private readonly ResultStorage $results,
        private readonly MapLocationRepository $locations,
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        MapSelection::configure($this, 'Map ids or ranges. All results by default');
        $this
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file (must be in the var directory to resolve images)', $this->config->varDir . '/report.html')
            ->addOption('biome', 'b', InputOption::VALUE_REQUIRED, 'Only display maps of this biome ("none" for maps without biome)')
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $selection = MapSelection::fromInput($input, $this->locations);
        $biome = $input->getOption('biome');
        $file = (string) $input->getOption('output');

        $results = array_filter(
            $this->results->all(),
            static fn (MapResult $result) => $selection->matches($result->id)
                && ($biome === null || ($result->classification !== null && ($result->classification->biome->value ?? 'none') === $biome)),
        );

        $locations = $this->locations->tryAll(static fn (string $error) => $io->warning('Locations not displayed: ' . $error));
        $subAreas = new SubAreaBiomeResolver()->resolve($locations, $this->results->all());
        $imagesPath = $this->relativeRendersPath(dirname($file));
        $cards = [];
        $areas = [];

        foreach ($results as $result) {
            $location = $locations[$result->id] ?? null;
            $cards[] = $this->card($result, $location, $location ? $subAreas[$location->subAreaId] : null, $imagesPath);

            if ($location?->areaName !== null) {
                $areas[$location->areaName] = true;
            }
        }

        ksort($areas);
        file_put_contents($file, $this->page(implode("\n", $cards), count($results), array_keys($areas)));
        $io->success(sprintf('Report of %d maps written to %s', count($results), $file));

        return self::SUCCESS;
    }

    private function card(MapResult $result, ?MapLocation $location, ?SubAreaBiome $subArea, string $imagesPath): string
    {
        $e = static fn (?string $value) => htmlspecialchars((string) $value);
        $c = $result->classification;

        if ($c === null) {
            $body = sprintf('<p class="error">%s: %s</p>', $e($result->error?->value), $e($result->errorMessage));
        } else {
            $tags = implode('', array_map(static fn (string $tag) => '<button type="button" class="tag">' . $e($tag) . '</button>', $c->tags));
            $body = sprintf(
                '<p><b>%s</b>%s · %s · %s</p><p>%s</p><p>%s</p>',
                $e($c->biome->value ?? 'no biome'),
                $c->biomeOther ? ' (' . $e($c->biomeOther) . ')' : '',
                $e($c->locationType->value),
                $c->indoor ? 'indoor' : 'outdoor',
                $tags,
                $e($c->description),
            );
        }

        $place = $location === null ? '' : sprintf(
            '<p class="location">%s › %s <small>(sub area: %s)</small></p>',
            $e($location->areaName ?? '?'),
            $e($location->subAreaName ?? '?'),
            $e($subArea?->biome->value ?? 'unknown'),
        );

        // Lowercase text used by the full text search
        $search = mb_strtolower(implode(' ', [
            $result->id,
            $location?->areaName,
            $location?->subAreaName,
            $c?->biomeOther,
            implode(' ', $c?->tags ?? []),
            $c?->description,
            $result->errorMessage,
        ]));

        return sprintf(
            '<figure data-status="%s" data-biome="%s" data-location="%s" data-indoor="%s" data-area="%s" data-search="%s"><img loading="lazy" src="%s/%d.png" alt="map %d"><figcaption><h2>#%d</h2>%s%s</figcaption></figure>',
            $result->ok ? 'ok' : 'error',
            $e($c === null ? '' : ($c->biome->value ?? 'none')),
            $e($c?->locationType->value ?? ''),
            $c === null ? '' : ($c->indoor ? 'indoor' : 'outdoor'),
            $e($location?->areaName ?? ''),
            $e($search),
            $e($imagesPath),
            $result->id,
            $result->id,
            $result->id,
            $place,
            $body,
        );
    }

    private function relativeRendersPath(string $reportDir): string
    {
        if (str_starts_with($this->config->rendersDir, $reportDir . '/')) {
            return substr($this->config->rendersDir, strlen($reportDir) + 1);
        }

        return 'file://' . $this->config->rendersDir;
    }

    /**
     * @param list<string> $areas
     */
    private function page(string $cards, int $count, array $areas = []): string
    {
        $options = static fn (array $cases) => implode('', array_map(
            static fn (BackedEnum $case) => sprintf('<option value="%1$s">%1$s</option>', $case->value),
            $cases,
        ));
        $biomes = $options(Biome::cases());
        $locations = $options(LocationType::cases());
        $areaOptions = implode('', array_map(
            static fn (string $area) => sprintf('<option value="%1$s">%1$s</option>', htmlspecialchars($area)),
            $areas,
        ));

        return <<<HTML
            <!doctype html>
            <html lang="en">
            <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Map classification</title>
            <style>
                body { font-family: system-ui, sans-serif; margin: 0 16px 16px; background: #f4f4f4; color: #222; }
                header { position: sticky; top: 0; z-index: 1; background: #f4f4f4; padding: 12px 0; border-bottom: 1px solid #ddd; margin-bottom: 16px; }
                h1 { margin: 0 0 8px; font-size: 20px; }
                form { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
                input, select, button { font: inherit; padding: 4px 8px; border: 1px solid #bbb; border-radius: 4px; background: #fff; }
                input[type=search] { flex: 1 1 240px; }
                #count { margin-left: auto; color: #555; }
                main { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 16px; }
                figure { margin: 0; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 1px 3px #0002; }
                figure[hidden] { display: none; }
                img { width: 100%; display: block; }
                figcaption { padding: 8px 12px; font-size: 14px; }
                h2 { margin: 0 0 4px; font-size: 16px; }
                p { margin: 4px 0; }
                .tag { display: inline-block; background: #e6eef8; border: 0; border-radius: 3px; padding: 1px 6px; margin: 2px; font-size: 12px; cursor: pointer; }
                .tag:hover { background: #cddcf0; }
                .error { color: #b00; }
                .location { color: #555; }
            </style>
            </head>
            <body>
            <header>
                <h1>Map classification</h1>
                <form id="filters" onsubmit="return false">
                    <input type="search" name="q" placeholder="Search id, tags, description… (all words must match)" autofocus>
                    <select name="area"><option value="">All areas</option>{$areaOptions}</select>
                    <select name="biome"><option value="">All biomes</option>{$biomes}<option value="none">(none)</option></select>
                    <select name="location"><option value="">All locations</option>{$locations}</select>
                    <select name="indoor"><option value="">Indoor &amp; outdoor</option><option value="indoor">Indoor</option><option value="outdoor">Outdoor</option></select>
                    <select name="status"><option value="">All statuses</option><option value="ok">OK</option><option value="error">Error</option></select>
                    <button type="reset">Reset</button>
                    <span id="count">{$count} / {$count} maps</span>
                </form>
            </header>
            <main>
            {$cards}
            </main>
            <script>
            (() => {
                const form = document.getElementById('filters');
                const count = document.getElementById('count');
                const cards = [...document.querySelectorAll('main figure')];
                const fields = ['area', 'biome', 'location', 'indoor', 'status'];

                function apply() {
                    const words = form.q.value.toLowerCase().split(/\s+/).filter(Boolean);
                    let visible = 0;

                    for (const card of cards) {
                        const show = fields.every(f => !form[f].value || card.dataset[f] === form[f].value)
                            && words.every(w => card.dataset.search.includes(w));

                        card.hidden = !show;
                        visible += show;
                    }

                    count.textContent = visible + ' / ' + cards.length + ' maps';

                    // Keep filters in the URL to share or reload them
                    const params = new URLSearchParams();
                    for (const name of ['q', ...fields]) {
                        if (form[name].value) params.set(name, form[name].value);
                    }
                    history.replaceState(null, '', params.size ? '#' + params : location.pathname);
                }

                const initial = new URLSearchParams(location.hash.slice(1));
                for (const [name, value] of initial) {
                    if (form[name]) form[name].value = value;
                }

                form.addEventListener('input', apply);
                form.addEventListener('reset', () => setTimeout(apply));
                document.querySelector('main').addEventListener('click', e => {
                    if (e.target.classList.contains('tag')) {
                        form.q.value = e.target.textContent;
                        apply();
                        window.scrollTo({ top: 0 });
                    }
                });

                apply();
            })();
            </script>
            </body>
            </html>
            HTML;
    }
}
