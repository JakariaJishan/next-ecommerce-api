<?php

namespace App\Jobs;

use App\Events\NotificationCreated;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class StoreNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $type;
    protected $content;
    protected $notifiableType;
    protected $notifiableId;
    protected $target;
    protected $specificUserIds;
    protected $status;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $type,
        string $content,
        string $notifiableType,
        int $notifiableId,
        string $target = 'all',
        array $specificUserIds = [],
        string $status = 'unread'
    ) {
        $this->type = $type;
        $this->content = $content;
        $this->notifiableType = $notifiableType;
        $this->notifiableId = $notifiableId;
        $this->target = $target;
        $this->specificUserIds = $specificUserIds;
        $this->status = $status;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // Fetch users based on the target audience
        $usersQuery = User::query();

        switch ($this->target) {
            case 'non-admins':
                $usersQuery->whereDoesntHave('roles', function ($query) {
                    $query->where('name', 'admin');
                });
                break;
            case 'admins':
                $usersQuery->whereHas('roles', function ($query) {
                    $query->where('name', 'admin');
                });
                break;
            case 'specific':
                $usersQuery->whereIn('id', $this->specificUserIds);
                break;
            case 'except-specific':
                $usersQuery->whereNotIn('id', $this->specificUserIds);
                break;
            case 'all':
            default:
                // No additional filtering for 'all'
                break;
        }

        $users = $usersQuery->get();
        $userIds = $users->pluck('id')->toArray(); // Extract user IDs

        // Prepare notifications
        $notifications = $users->map(function ($user) {
            return [
                'user_id' => $user->id,
                'notifiable_type' => $this->notifiableType,
                'notifiable_id' => $this->notifiableId,
                'type' => $this->type,
                'status' => $this->status,
                'content' => $this->content,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        // Insert notifications and broadcast them
        if (!empty($notifications)) {
            DB::table('notifications')->insert($notifications);

            $notificationIds = DB::table('notifications')
                ->whereIn('user_id', $userIds)
                ->where('notifiable_type', $this->notifiableType)
                ->where('notifiable_id', $this->notifiableId)
                ->where('type', $this->type)
                ->where('content', $this->content)
                ->pluck('id')
                ->toArray();

            // Dispatch with status included
            NotificationCreated::dispatch(
                $userIds,
                $notificationIds,
                $this->status, // Add status here
                $this->type,
                $this->content,
                $this->notifiableType,
                $this->notifiableId
            );
        }
    }
}
