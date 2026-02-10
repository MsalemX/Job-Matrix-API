<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'project_id', 'section_id', 'name', 'description', 'skills', 
        'assigned_to', 'deadline', 'status', 'points', 'completed_at', 'is_archived'
    ];

    protected $casts = [
        'skills' => 'json',
        'deadline' => 'date',
        'completed_at' => 'datetime',
        'is_archived' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function section()
    {
        return $this->belongsTo(ProjectSection::class, 'section_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function attachments()
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function dependencies()
    {
        return $this->hasMany(TaskDependency::class);
    }
}
