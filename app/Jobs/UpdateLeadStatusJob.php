<?php

namespace App\Jobs;

use App\Models\Lead;
use Core\Lead\Application\DTOs\Requests\UpdateLeadStatusQueueDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateLeadStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly UpdateLeadStatusQueueDTO $dto
    ) {}

    public function handle(): void
    {
        // ব্যাকগ্রাউন্ডে লিডের স্ট্যাটাস আপডেট করা হচ্ছে
        Lead::where('id', $this->dto->leadId)
            ->where('status', 'new')
            ->update(['status' => $this->dto->status]);
    }
}