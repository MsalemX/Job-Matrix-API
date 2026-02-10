<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = ['user_id', 'name', 'description', 'visibility', 'skills', 'invite_link', 'is_archived'];

    protected $casts = [
        'skills' => 'json',
        'is_archived' => 'boolean',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function participants()
    {
        return $this->hasMany(ProjectParticipant::class);
    }

    public function sections()
    {
        return $this->hasMany(ProjectSection::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}
