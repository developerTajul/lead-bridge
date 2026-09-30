<?php

namespace App\Jobs;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessInboundLead implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public array $payload) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // ব্যাকগ্রাউন্ডে নিরাপদে লিড তৈরি করা
        Lead::create([
            'name'         => $this->payload['name'],
            'email'        => $this->payload['email'] ?? null,
            'phone'        => $this->payload['phone'] ?? null,
            'company_name' => $this->payload['company_name'] ?? null,
            'source'       => $this->payload['source'] ?? 'api_webhook',
            'status'       => 'new',
        ]);
    }
}