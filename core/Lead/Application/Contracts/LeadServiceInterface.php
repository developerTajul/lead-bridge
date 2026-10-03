<?php

declare(strict_types=1);

namespace Core\Lead\Application\Contracts;

use Core\Lead\Application\DTOs\Requests\LeadCreateDTO;
use Core\Shared\Application\DTOs\Result;

/**
 * Service contract for lead related use cases.
 */
interface LeadServiceInterface
{
    /**
     * Captures an inbound lead by dispatching it to the background queue.
     */
    public function captureLead(LeadCreateDTO $dto): void;

    /**
     * Creates a lead directly via the repository.
     */
    public function createLead(LeadCreateDTO $leadCreateDTO): Result;
}
