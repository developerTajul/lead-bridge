<?php

declare(strict_types=1);

namespace App\Jobs;

use Core\Lead\Application\Contracts\LeadServiceInterface;
use Core\Lead\Application\DTOs\Requests\LeadCreateDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessInboundLead implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly LeadCreateDTO $dto) {}

    public function handle(LeadServiceInterface $leadService): void
    {
        $leadService->createLead($this->dto);
    }
}
