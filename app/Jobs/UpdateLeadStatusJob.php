<?php

declare(strict_types=1);

namespace App\Jobs;

use Core\Lead\Application\Contracts\LeadServiceInterface;
use Core\Lead\Application\DTOs\Requests\UpdateLeadStatusQueueDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Applies a queued status change through the lead service, so the domain
 * rules decide whether the change is allowed.
 */
final class UpdateLeadStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly UpdateLeadStatusQueueDTO $dto
    ) {}

    /**
     * @param LeadServiceInterface $leadService
     *
     * @return void
     */
    public function handle(LeadServiceInterface $leadService): void
    {
        $leadService->changeStatus($this->dto->leadId, $this->dto->status);
    }
}
