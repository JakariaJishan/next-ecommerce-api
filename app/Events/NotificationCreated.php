<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userIds; // Change to array
    public $notificationIds; // Add notification IDs
    public $type;
    public $content;
    public $notifiableType;
    public $notifiableId;
    public $status;

    /**
     * Create a new event instance.
     */
    public function __construct(array $userIds, array $notificationIds, $status, $type, $content, $notifiableType, $notifiableId)
    {
        $this->userIds = $userIds; // Array of user IDs
        $this->notificationIds = $notificationIds;
        $this->type = $type;
        $this->content = $content;
        $this->notifiableType = $notifiableType;
        $this->notifiableId = $notifiableId;
        $this->status = $status;
        \Log::info("Broadcasting notification for users " . implode(', ', $userIds) . ": {$content}");
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel
     */
    public function broadcastOn()
    {
        return new Channel('notifications');
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'notification.created';
    }

    /**
     * The data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'user_ids' => $this->userIds,
            'id' => $this->notificationIds, // Add notification IDs to payload
            'type' => $this->type,
            'content' => $this->content,
            'notifiable_type' => $this->notifiableType,
            'notifiable_id' => $this->notifiableId,
            'status' => $this->status,
        ];
    }
}
