<?php
declare(strict_types=1);

namespace App\Listeners;

use App\Events\ActivityCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateLeadStausOnActivityCreaed
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ActivityCreated $event): void
    {
        //
    }
}
