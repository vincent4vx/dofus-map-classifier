<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Classification\Export;

use MapClassifier\Infrastructure\Export\ExportFormat;
use MapClassifier\Infrastructure\Filesystem\Path;
use MapClassifier\Map\Classification\Model\Biome;
use MapClassifier\Map\Classification\Model\LocationType;
use MapClassifier\Sprite\Classification\Model\Export\SpriteExport;
use MapClassifier\Sprite\Classification\Model\SpriteCategory;
use MapClassifier\Sprite\Model\Layer;
use MapClassifier\Sprite\Model\LocationUsage;
use Override;

use function array_column;
use function array_keys;
use function array_map;
use function count;
use function dirname;
use function file_put_contents;
use function htmlspecialchars;
use function implode;
use function json_encode;
use function sort;
use function sprintf;
use function str_replace;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * HTML page displaying sprites with their aggregated data and type, with filters
 * Sprites are rendered by JavaScript from a compact JSON list, with pagination
 *
 * @implements ExportFormat<SpriteExport>
 */
final readonly class HtmlSpriteExportFormat implements ExportFormat
{
    public function __construct(
        /** Directory of the sprites images */
        private string $spritesDir,
        private string $defaultOutput,
    ) {}

    #[Override]
    public function name(): string
    {
        return 'html';
    }

    #[Override]
    public function defaultOutput(): string
    {
        return $this->defaultOutput;
    }

    #[Override]
    public function write(object $export, string $output): array
    {
        file_put_contents($output, $this->render($export, Path::relativeUrl(dirname($output), $this->spritesDir)));

        return [$output];
    }

    /**
     * @param string $spritesPath Path or URL of the sprites images directory, from the page
     */
    public function render(SpriteExport $export, string $spritesPath): string
    {
        // Compact list for the page: [set, id, layer, occurrences, maps, classified maps, areas, sub areas, biomes, indoor, location types, type, interactive]
        $items = [];
        $areas = [];
        $interactiveTypes = [];
        $named = static fn (array $values) => array_map(static fn (LocationUsage $usage) => [$usage->label(), $usage->maps], $values);

        foreach ($export->sprites as $sprites) {
            foreach ($sprites as $sprite) {
                $profile = $sprite->profile;
                $type = $sprite->type;

                $items[] = [
                    $profile->set->value,
                    $profile->id,
                    $profile->layer->value,
                    $profile->occurrences,
                    $profile->maps,
                    $profile->classifiedMaps,
                    $named($profile->areas),
                    $named($profile->subAreas),
                    (object) $profile->biomes,
                    (object) $profile->indoor,
                    (object) $profile->locationTypes,
                    $type === null ? null : [$type->category->value, $type->name, $type->keywords, $type->description],
                    $profile->interactive === null ? null : [$profile->interactive->name, $profile->interactive->typeName()],
                ];

                if ($profile->interactive !== null) {
                    $interactiveTypes[$profile->interactive->typeName()] = true;
                }

                foreach ($profile->areas as $area) {
                    $areas[$area->label()] = true;
                }
            }
        }

        $areas = array_keys($areas);
        sort($areas);
        $interactiveTypes = array_keys($interactiveTypes);
        sort($interactiveTypes);

        return $this->page(
            json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $spritesPath,
            $areas,
            $interactiveTypes,
        );
    }

    /**
     * @param list<string> $areas
     * @param list<string> $interactiveTypes
     */
    private function page(string $json, string $spritesPath, array $areas, array $interactiveTypes = []): string
    {
        $options = static fn (array $values) => implode('', array_map(
            static fn (string $value) => sprintf('<option value="%1$s">%1$s</option>', htmlspecialchars($value)),
            $values,
        ));
        $layers = $options(array_column(Layer::cases(), 'value'));
        $biomes = $options(array_column(Biome::cases(), 'value'));
        $locations = $options(array_column(LocationType::cases(), 'value'));
        $categories = $options(array_column(SpriteCategory::cases(), 'value'));
        $areaOptions = $options($areas);
        $interactiveOptions = $options($interactiveTypes);
        // Avoid closing the script tag from the data
        $json = str_replace('</', '<\/', $json);
        $spritesPath = htmlspecialchars(json_encode($spritesPath, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return <<<HTML
            <!doctype html>
            <html lang="en">
            <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Map sprites</title>
            <style>
                body { font-family: system-ui, sans-serif; margin: 0 16px 16px; background: #f4f4f4; color: #222; }
                header { position: sticky; top: 0; z-index: 1; background: #f4f4f4; padding: 12px 0; border-bottom: 1px solid #ddd; margin-bottom: 16px; }
                h1 { margin: 0 0 8px; font-size: 20px; }
                form { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
                input, select, button { font: inherit; padding: 4px 8px; border: 1px solid #bbb; border-radius: 4px; background: #fff; }
                input[type=search] { flex: 1 1 220px; }
                input[type=number] { width: 90px; }
                #count { margin-left: auto; color: #555; }
                main { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; }
                article { background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 1px 3px #0002; font-size: 13px; }
                .sprite { height: 160px; display: flex; align-items: center; justify-content: center; background: #a0a0a0; }
                .sprite img { max-width: 100%; max-height: 100%; image-rendering: auto; }
                .info { padding: 8px 10px; }
                h2 { margin: 0 0 4px; font-size: 15px; }
                h2 small { font-weight: normal; color: #666; }
                p { margin: 3px 0; }
                .chip { display: inline-block; background: #e6eef8; border-radius: 3px; padding: 0 5px; margin: 1px; }
                .chip b { font-weight: 600; }
                .label { color: #666; }
                #more { display: block; margin: 16px auto; }
                .type { font-size: 14px; }
                .cat { background: #333; color: #fff; border-radius: 3px; padding: 0 6px; font-size: 12px; }
                .kw { display: inline-block; background: #fff3d6; border: 0; border-radius: 3px; padding: 0 5px; margin: 1px; font: inherit; cursor: pointer; }
                .kw:hover { background: #ffe3a0; }
                .io { background: #2a7a3b; color: #fff; border-radius: 3px; padding: 0 6px; font-size: 12px; }
            </style>
            </head>
            <body>
            <header>
                <h1>Map sprites</h1>
                <form id="filters" onsubmit="return false">
                    <input type="search" name="q" placeholder="Search id, name, category, keyword, interactive object…" autofocus>
                    <select name="category"><option value="">All categories</option>{$categories}<option value="none">(not classified)</option></select>
                    <select name="interactive"><option value="">Interactive or not</option><option value="any">Interactive objects</option>{$interactiveOptions}<option value="none">Not interactive</option></select>
                    <select name="set"><option value="">All sets</option><option value="grounds">grounds</option><option value="objects">objects</option></select>
                    <select name="layer"><option value="">All layers</option>{$layers}</select>
                    <select name="biome"><option value="">All biomes</option>{$biomes}</select>
                    <select name="area"><option value="">All areas</option>{$areaOptions}</select>
                    <select name="indoor"><option value="">Indoor &amp; outdoor</option><option value="indoor">Indoor</option><option value="outdoor">Outdoor</option></select>
                    <select name="location"><option value="">All locations</option>{$locations}</select>
                    <label>Min maps <input type="number" name="min" min="0" value="0"></label>
                    <select name="sort"><option value="maps">Sort: maps</option><option value="occurrences">Sort: occurrences</option><option value="id">Sort: id</option></select>
                    <button type="reset">Reset</button>
                    <span id="count"></span>
                </form>
            </header>
            <main></main>
            <button id="more" type="button">Show more</button>
            <script id="data" type="application/json">{$json}</script>
            <script>
            (() => {
                const SPRITES = JSON.parse('{$spritesPath}'.replace(/&quot;/g, '"'));
                const PAGE = 200;
                const items = JSON.parse(document.getElementById('data').textContent).map(i => ({
                    set: i[0], id: i[1], layer: i[2], occurrences: i[3], maps: i[4], classified: i[5],
                    areas: i[6], subAreas: i[7], biomes: i[8], indoor: i[9], locations: i[10], type: i[11], interactive: i[12],
                    // Search only on the sprite identity and type: other attributes have their own filter
                    search: ((i[0] === 'grounds' ? 'g' : 'o') + i[1] + ' ' + i[1]
                        + (i[11] ? ' ' + i[11][0] + ' ' + i[11][1] + ' ' + i[11][2].join(' ') : '')
                        + (i[12] ? ' ' + i[12][0] + ' ' + i[12][1] : '')).toLowerCase(),
                }));
                const form = document.getElementById('filters');
                const main = document.querySelector('main');
                const count = document.getElementById('count');
                const more = document.getElementById('more');
                let matching = [];
                let shown = 0;

                const esc = s => String(s).replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'})[c]);
                const chips = obj => Object.entries(obj).map(([k, v]) => '<span class="chip">' + esc(k) + ' <b>' + v + '</b></span>').join('');
                const list = (values, max) => values.slice(0, max).map(([n, c]) => '<span class="chip">' + esc(n) + ' <b>' + c + '</b></span>').join('')
                    + (values.length > max ? ' <span class="label">+' + (values.length - max) + '</span>' : '');

                function card(i) {
                    return '<article><div class="sprite"><img loading="lazy" src="' + esc(SPRITES + '/' + i.set + '/' + i.id + '.png') + '" alt="' + i.id + '"></div>'
                        + '<div class="info"><h2>' + (i.set === 'grounds' ? 'g' : 'o') + i.id + ' <small>' + i.layer + ' · ' + i.maps + ' maps · ' + i.occurrences + ' uses</small></h2>'
                        + (i.type ? '<p class="type"><b>' + esc(i.type[1]) + '</b> <span class="cat">' + esc(i.type[0]) + '</span></p>'
                            + '<p>' + i.type[2].map(k => '<button type="button" class="kw">' + esc(k) + '</button>').join('') + '</p>' : '')
                        + (i.interactive ? '<p><span class="io" title="Interactive object (langs)">' + esc(i.interactive[1]) + ': ' + esc(i.interactive[0]) + '</span></p>' : '')
                        + (Object.keys(i.biomes).length ? '<p><span class="label">Biomes</span> ' + chips(i.biomes) + '</p>' : '')
                        + (i.areas.length ? '<p><span class="label">Areas</span> ' + list(i.areas, 4) + '</p>' : '')
                        + (i.subAreas.length ? '<p><span class="label">Sub areas</span> ' + list(i.subAreas, 4) + '</p>' : '')
                        + (i.classified ? '<p><span class="label">' + i.classified + ' classified maps</span> ' + chips(i.indoor) + chips(i.locations) + '</p>' : '')
                        + '</div></article>';
                }

                function showMore() {
                    main.insertAdjacentHTML('beforeend', matching.slice(shown, shown + PAGE).map(card).join(''));
                    shown = Math.min(shown + PAGE, matching.length);
                    more.hidden = shown >= matching.length;
                }

                function apply() {
                    const f = Object.fromEntries(new FormData(form));
                    const words = f.q.toLowerCase().split(/\s+/).filter(Boolean);
                    const min = parseInt(f.min || '0', 10);

                    matching = items.filter(i => (!f.set || i.set === f.set)
                        && (!f.layer || i.layer === f.layer)
                        && (!f.interactive || (f.interactive === 'any' ? i.interactive : f.interactive === 'none' ? !i.interactive : i.interactive && i.interactive[1] === f.interactive))
                        && (!f.category || (f.category === 'none' ? !i.type : i.type && i.type[0] === f.category))
                        && (!f.biome || f.biome in i.biomes)
                        && (!f.area || i.areas.some(a => a[0] === f.area))
                        && (!f.indoor || f.indoor in i.indoor)
                        && (!f.location || f.location in i.locations)
                        && i.maps >= min
                        && words.every(w => i.search.includes(w)));

                    const key = f.sort;
                    matching.sort(key === 'id' ? (a, b) => a.set.localeCompare(b.set) || a.id - b.id : (a, b) => b[key] - a[key]);

                    count.textContent = matching.length + ' / ' + items.length + ' sprites';
                    main.innerHTML = '';
                    shown = 0;
                    showMore();

                    const params = new URLSearchParams();
                    for (const [k, v] of Object.entries(f)) {
                        if (v && !(k === 'min' && v === '0') && !(k === 'sort' && v === 'maps')) params.set(k, v);
                    }
                    history.replaceState(null, '', params.size ? '#' + params : location.pathname);
                }

                for (const [name, value] of new URLSearchParams(location.hash.slice(1))) {
                    if (form[name]) form[name].value = value;
                }

                form.addEventListener('input', apply);
                form.addEventListener('reset', () => setTimeout(apply));
                more.addEventListener('click', showMore);
                main.addEventListener('click', e => {
                    if (e.target.classList.contains('kw')) {
                        form.q.value = e.target.textContent;
                        apply();
                        window.scrollTo({ top: 0 });
                    }
                });
                new IntersectionObserver(entries => entries[0].isIntersecting && !more.hidden && showMore()).observe(more);
                apply();
            })();
            </script>
            </body>
            </html>
            HTML;
    }
}
