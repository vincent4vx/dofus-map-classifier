<?php

declare(strict_types=1);

namespace MapClassifier\Map\Classification\Repository;

use MapClassifier\Map\Classification\Model\ErrorCode;
use MapClassifier\Map\Classification\Model\MapResult;
use MapClassifier\Map\Exception\RenderException;
use MapClassifier\Map\Model\MapFile;
use MapClassifier\Map\Model\RenderError;
use MapClassifier\Map\Repository\RenderErrorRepository;
use Override;

/**
 * Store render errors as map results: the maps are listed in error by the export, and never classified
 */
final readonly class ResultRenderErrorRepository implements RenderErrorRepository
{
    public function __construct(
        private MapResultRepository $results,
    ) {}

    #[Override]
    public function get(int $id): ?RenderError
    {
        return $this->results->get($id)?->error?->renderError();
    }

    #[Override]
    public function save(MapFile $map, RenderException $error): void
    {
        $this->results->save(MapResult::failure($map->id, $map->version, ErrorCode::fromRenderError($error->error), $error->getMessage()));
    }

    #[Override]
    public function delete(int $id): void
    {
        // Only delete render errors: other results are not related to the rendering
        if ($this->get($id) !== null) {
            $this->results->delete($id);
        }
    }
}
