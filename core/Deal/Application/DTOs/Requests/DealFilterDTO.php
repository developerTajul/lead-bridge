<?php

declare(strict_types=1);

namespace Core\Deal\Application\DTOs\Requests;

/**
 * Carries validated delivery-layer query input INTO the Service. Immutable.
 */
final readonly class DealFilterDTO
{
    /**
     * @param int|null $pipelineId
     * @param int|null $dealStageId
     * @param int      $perPage
     */
    public function __construct(
        public ?int $pipelineId = null,
        public ?int $dealStageId = null,
        public int $perPage = 15,
    ) {}
}
