<?php

namespace App\Events;

use App\Models\SalesOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SalesOrderStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public SalesOrder $salesOrder,
        public string $oldStatus,
        public string $newStatus
    ) {
        //
    }
}
