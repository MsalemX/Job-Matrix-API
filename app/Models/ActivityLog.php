<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'project_id', 'action', 'loggable_type', 
        'loggable_id', 'description', 'ip_address', 'metadata'
    ];

    protected $casts = [
        'metadata' => 'json'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function loggable()
    {
        return $this->morphTo();
    }
}
