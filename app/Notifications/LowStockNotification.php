<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $inventory;

    /**
     * Create a new notification instance.
     */
    public function __construct($inventory)
    {
        $this->inventory = $inventory;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['database'];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase($notifiable)
    {
        return new DatabaseMessage([
            'message' => "Product '{$this->inventory->product->name}' (SKU: {$this->inventory->product->sku}) has low stock: {$this->inventory->stock_quantity} units remaining.",
            'product_id' => $this->inventory->product_id,
            'stock_quantity' => $this->inventory->stock_quantity,
            'low_stock_threshold' => $this->inventory->low_stock_threshold,
        ]);
    }
}
