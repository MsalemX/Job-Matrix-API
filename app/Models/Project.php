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

    /**
     * Scope a query to search projects by term across name, description, skills, and owner.
     */
    public function scopeSearch($query, $term)
    {
        if (! $term) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'LIKE', "%{$term}%")
                ->orWhere('description', 'LIKE', "%{$term}%")
                ->orWhereJsonContains('skills', $term)
                ->orWhereHas('owner', function ($q2) use ($term) {
                    $q2->where('name', 'LIKE', "%{$term}%")
                        ->orWhere('username', 'LIKE', "%{$term}%");
                });
        });
    }

    /**
     * Scope a query to filter projects by skill(s).
     * Accepts a single skill string, a comma-separated string, or an array of skills.
     * Matches when any of the provided skills exist in the project's `skills` JSON field.
     */
    public function scopeFilterBySkills($query, $skills)
    {
        if (! $skills) {
            return $query;
        }

        if (is_string($skills)) {
            $skills = array_map('trim', explode(',', $skills));
        }

        $skills = array_filter((array) $skills);

        if (empty($skills)) {
            return $query;
        }

        return $query->where(function ($q) use ($skills) {
            foreach ($skills as $skill) {
                $q->orWhereJsonContains('skills', $skill);
            }
        });
    }
}
