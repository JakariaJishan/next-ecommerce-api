<?php

namespace App\Services;

use App\Jobs\StoreNotifications;

class NotificationService
{
    /**
     * Dispatch a job to store notifications for targeted users.
     *
     * @param string $type The type of notification (e.g., 'message', 'new_ad', etc.)
     * @param string $content The content of the notification
     * @param string $notifiableType The model type (e.g., 'App\Models\Contest')
     * @param int $notifiableId The ID of the notifiable entity
     * @param string $target The target audience ('all', 'non-admins', 'admins', 'specific', 'except-specific')
     * @param array $specificUserIds Optional array of user IDs for 'specific' or 'except-specific' targets
     * @param string $status The status of the notification (default: 'unread')
     * @return bool
     */
    public function storeNotification(
        string $type,
        string $content,
        string $notifiableType,
        int $notifiableId,
        string $target = 'all',
        array $specificUserIds = [],
        string $status = 'unread'
    ): bool {
        try {
            // Dispatch the job to the queue
            StoreNotifications::dispatch(
                $type,
                $content,
                $notifiableType,
                $notifiableId,
                $target,
                $specificUserIds,
                $status
            );

            return true;
        } catch (\Exception $e) {
            // Log the error if needed: \Log::error($e->getMessage());
            return false;
        }
    }
}
