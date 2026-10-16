<?php

declare(strict_types=1);

namespace Core\Lead\Application\Listeners;

use Core\Lead\Application\DTOs\Requests\UpdateLeadStatusQueueDTO;
use Core\Activity\Domain\Events\ActivityCreatedEvent;
use Core\Shared\Application\Contracts\QueueManagerContract;

final class UpdateLeadStatusOnActivityCreated
{
    public function __construct(
        private readonly QueueManagerContract $queueManager
    ) {}

    /**
     * @param ActivityCreatedEvent $event
     */
    public function handle(ActivityCreatedEvent $event): void
    {
        if ($event->trackableType === 'App\Models\Lead' || $event->trackableType === 'Lead') {
            $queueData = new UpdateLeadStatusQueueDTO(
                leadId: $event->trackableId,
                status: 'contacted'
            );

            $this->queueManager->push('lead_status_update_topic', $queueData);
        }
    }
}