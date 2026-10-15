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
        // ১. চেক করা হচ্ছে অ্যাক্টিভিটিটি লিড (Lead) মডিউলের জন্য কি না
        // (হার্ডকোডেড স্ট্রিংয়ের বদলে কনফিগারেশন বা ডোমেইন কনস্ট্যান্ট ব্যবহার করা আরও ভালো)
        if ($event->trackableType === 'App\Models\Lead' || $event->trackableType === 'Lead') {
            
            // ২. কিউ-এর জন্য DTO তৈরি
            $queueData = new UpdateLeadStatusQueueDTO(
                leadId: $event->trackableId,
                status: 'contacted'
            );

            // ৩. ব্যাকগ্রাউন্ড কিউ-তে জব পুশ করা
            $this->queueManager->push('lead_status_update_topic', $queueData);
        }
    }
}