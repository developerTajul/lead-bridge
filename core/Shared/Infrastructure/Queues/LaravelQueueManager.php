<?php 
declare(strict_types=1);

namespace Core\Shared\Infrastructure\Queues;
use Core\Shared\Application\Contracts\QueueManagerContract;
use Core\Shared\Application\DTOs\WriteDTO;

class LaravelQueueManager implements QueueManagerContract {
    public function push(string $jobIdentifier, WriteDTO $data): void
    {
        $jobClass = match ($jobIdentifier) {
            'lead.inbound.process' => \App\Jobs\ProcessInboundLead::class,
            default => throw new \InvalidArgumentException("Unrecognized job identifier: {$jobIdentifier}"),
        };

        dispatch(new $jobClass($data));
    }
}