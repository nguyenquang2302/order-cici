<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPaymentUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $channelName,
        public array $orderIds,
        public ?int $paymentGroupId = null
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel($this->channelName),
            new Channel('admin.orders'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'order_ids' => $this->orderIds,
            'payment_group_id' => $this->paymentGroupId,
            'status' => 'updated',
        ];
    }
}