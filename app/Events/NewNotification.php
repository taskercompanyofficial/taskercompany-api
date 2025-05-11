<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class NewNotification implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public $title;
    public $message;
    public $type;
    public $link;
    public $time;

    public function __construct($title, $message, $type, $link)
    {
        $this->title = $title;
        $this->message = $message;
        $this->type = $type;
        $this->link = $link;
        $this->time = now()->toDateTimeString();
    }

    public function broadcastOn()
    {
        return new Channel('notification-channel'); // Public channel
    }

    public function broadcastAs()
    {
        return 'notification-event';
    }
}
