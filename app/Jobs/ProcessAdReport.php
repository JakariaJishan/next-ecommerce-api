<?php

namespace App\Jobs;

use App\Models\Ads;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ProcessAdReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $adId;

    public function __construct($adId)
    {
        $this->adId = $adId;
    }

    public function handle()
    {
        $ad = Ads::find($this->adId);

        if (!$ad || $ad->moderation_status === 'rejected') {
            return; // Skip if ad doesn’t exist or is already banned
        }

        // Increment the report count
        $ad->increment('report_count');

        // Update ad status based on report count
        if ($ad->report_count >= 50) {
            $ad->update([
                'moderation_status' => 'rejected',
                'status' => 'expired',
            ]);
        } elseif ($ad->report_count >= 10) {
            $ad->update([
                'moderation_status' => 'flagged',
            ]);
        }

        // Cache the updated report count and status (e.g., for 1 hour)
        Cache::put("ad:{$this->adId}:report_count", $ad->report_count, 3600);
        Cache::put("ad:{$this->adId}:moderation_status", $ad->moderation_status, 3600);
    }
}
