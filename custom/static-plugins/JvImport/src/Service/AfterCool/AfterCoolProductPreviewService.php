<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool;

use Jv\Import\Service\AfterCool\Contract\AfterCoolProductSourceInterface;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductPreviewPage;

final readonly class AfterCoolProductPreviewService
{
    public function __construct(private AfterCoolProductSourceInterface $source)
    {
    }

    public function execute(int $factoryId, int $limit, int $offset, ?string $query): AfterCoolProductPreviewPage
    {
        $page = $this->source->getProductPage($factoryId, $offset, $limit, $query);

        return new AfterCoolProductPreviewPage(
            $page->previewItems,
            $page->total,
            $limit,
            $page->offset,
            $page->hasMore,
        );
    }
}
