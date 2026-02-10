<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

trait LogsActivity
{
    /**
     * Log a specific activity.
     */
    protected function logActivity(string $action, ?Model $loggable = null, ?string $description = null, ?array $metadata = null, ?int $projectId = null)
    {
        return ActivityLog::create([
            'user_id' => auth()->id(),
            'project_id' => $projectId,
            'action' => $action,
            'loggable_id' => $loggable?->id,
            'loggable_type' => $loggable ? get_class($loggable) : null,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => request()->ip()
        ]);
    }
}
