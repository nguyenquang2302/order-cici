<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $orderId,
        public string $orderNumber,
        public ?int $pcId = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('admin.orders'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'pc_id' => $this->pcId,
        ];
    }
}
